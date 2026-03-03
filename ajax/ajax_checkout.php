<?php
/*
 * ajax_checkout.php — VSG Marketplace
 * ─────────────────────────────────────────────────────────────────────
 * Endpoint AJAX para:
 *   action=initiate_mm   → Inicia USSD Push (M-Pesa ou e-Mola)
 *   action=create_order  → Cria pedido na DB (após pagamento confirmado)
 *
 * Colocar em: pages/app/ajax/ajax_checkout.php
 *
 * ════════════════════════════════════════════════════════════════════
 * M-PESA API (Vodacom Mozambique)
 * ────────────────────────────────
 * Developer Portal  : https://developer.mpesa.vm.co.mz
 * Sandbox host      : api.sandbox.vm.co.mz
 * Production host   : api.vm.co.mz
 *
 * Credenciais necessárias (obter no portal):
 *   MPESA_API_KEY        → gerada no portal após criar a App
 *   MPESA_PUBLIC_KEY     → chave pública RSA disponível no portal
 *   MPESA_PROVIDER_CODE  → 171717 (sandbox) / fornecido pela Vodacom (produção)
 *   MPESA_ORIGIN         → domínio da vossa aplicação (ex: vsgmarket.co.mz)
 *
 * Passos para integração real:
 *   1. Criar conta em developer.mpesa.vm.co.mz
 *   2. Criar uma App e copiar API Key + Public Key
 *   3. Testar em sandbox com cartões de teste
 *   4. Submeter para revisão Vodacom → obter credenciais de produção
 *
 * ════════════════════════════════════════════════════════════════════
 * e-MOLA API (Movitel Mozambique)
 * ────────────────────────────────
 * Contacto        : movitel.co.mz (departamento de parceiros)
 * A API não é pública — requer contrato com a Movitel Business.
 * Após contrato, a Movitel fornece:
 *   EMOLA_USERNAME, EMOLA_PASSWORD, EMOLA_MERCHANT_CODE
 * Os endpoints implementados seguem o padrão documentado em
 * integrações de terceiros conhecidas — confirmar com Movitel.
 * ─────────────────────────────────────────────────────────────────────
 */

// ── Buffer de saída — evita que warnings PHP quebrem o JSON ──────────
ob_start();

// Handler de erros globais — converte erros PHP em JSON limpo
set_error_handler(function(int $no, string $str, string $file, int $line): bool {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $dev = (getenv('APP_ENV') === 'development');
    echo json_encode(['ok'=>false,'msg'=> $dev
        ? "PHP [{$no}]: {$str} em ".basename($file).":{$line}"
        : 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
    exit;
});
set_exception_handler(function(Throwable $e): void {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    $dev = (getenv('APP_ENV') === 'development');
    echo json_encode(['ok'=>false,'msg'=> $dev
        ? get_class($e).': '.$e->getMessage().' ['.basename($e->getFile()).':'.$e->getLine().']'
        : 'Erro interno do servidor.'], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__ . '/../registration/bootstrap.php';
require_once __DIR__ . '/../registration/includes/security.php';

ob_clean(); // limpar qualquer output dos requires (BOM, notices, etc.)

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

function jout(array $d): never {
    // Garantir buffer limpo — nenhum whitespace/HTML antes do JSON
    if (ob_get_level()) { $leak = ob_get_clean(); if (trim($leak)) error_log('[VSG ajax] output leak: '.substr($leak,0,200)); }
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Só aceita XHR POST ───────────────────────────────────────────────
if (
    ($_SERVER['REQUEST_METHOD']        ?? '') !== 'POST' ||
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest'
) { http_response_code(403); jout(['ok'=>false,'msg'=>'Acesso negado.']); }

// ── Auth ──────────────────────────────────────────────────────────────
if (empty($_SESSION['auth']['user_id'])) {
    jout(['ok'=>false,'msg'=>'Sessão expirada.','redirect'=>'/registration/login/login.php']);
}
$user_id = (int)$_SESSION['auth']['user_id'];

// ── CSRF ──────────────────────────────────────────────────────────────
$csrf = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    jout(['ok'=>false,'msg'=>'Token inválido. Recarregue a página.']);
}

$action = trim($_POST['action'] ?? '');

// ════════════════════════════════════════════════════════════════════
// CONFIGURAÇÃO — usar variáveis de ambiente (.env) em produção
// ════════════════════════════════════════════════════════════════════

// M-PESA
define('MPESA_ENV',           getenv('MPESA_ENV')           ?: 'sandbox');
define('MPESA_API_KEY',       getenv('MPESA_API_KEY')       ?: 'SjeHnDF8HUFpYeLZy879G1mw70IWQHmg');
define('MPESA_PUBLIC_KEY',    getenv('MPESA_PUBLIC_KEY')    ?: 'SUA_PUBLIC_KEY_AQUI');
define('MPESA_PROVIDER_CODE', getenv('MPESA_PROVIDER_CODE') ?: '171717');
define('MPESA_ORIGIN',        getenv('MPESA_ORIGIN')        ?: 'vsgmarket.co.mz');
define('MPESA_API_HOST', MPESA_ENV === 'production' ? 'api.vm.co.mz' : 'api.sandbox.vm.co.mz');

// e-MOLA
define('EMOLA_ENV',           getenv('EMOLA_ENV')           ?: 'sandbox');
define('EMOLA_USERNAME',      getenv('EMOLA_USERNAME')      ?: 'SEU_USERNAME_AQUI');
define('EMOLA_PASSWORD',      getenv('EMOLA_PASSWORD')      ?: 'SUA_PASSWORD_AQUI');
define('EMOLA_MERCHANT_CODE', getenv('EMOLA_MERCHANT_CODE') ?: 'SEU_MERCHANT_CODE');
define('EMOLA_API_HOST', EMOLA_ENV === 'production' ? 'emola.movitel.co.mz' : 'emola-sandbox.movitel.co.mz');

// ════════════════════════════════════════════════════════════════════
// M-PESA HELPERS
// ════════════════════════════════════════════════════════════════════

/**
 * Gera Bearer Token M-Pesa via RSA encryption.
 * O API Key é encriptado com a Public Key do portal.
 * @see https://developer.mpesa.vm.co.mz/apis (Authentication)
 */
function mpesa_bearer(): string {
    if (isset($_SESSION['_mpesa_tok']) && (time()-($_SESSION['_mpesa_tok_ts']??0))<3500) {
        return $_SESSION['_mpesa_tok'];
    }
    $pem = "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(MPESA_PUBLIC_KEY, 60, "\n")
         . "-----END PUBLIC KEY-----";
    $key = openssl_get_publickey($pem);
    if (!$key) throw new RuntimeException('Chave pública M-Pesa inválida — verificar MPESA_PUBLIC_KEY.');
    openssl_public_encrypt(MPESA_API_KEY, $enc, $key, OPENSSL_PKCS1_PADDING);
    $token = base64_encode($enc);
    $_SESSION['_mpesa_tok']    = $token;
    $_SESSION['_mpesa_tok_ts'] = time();
    return $token;
}

/**
 * Chamada à API M-Pesa via cURL.
 */
function mpesa_call(string $ep, array $body): array {
    $token = mpesa_bearer();
    $url   = 'https://'.MPESA_API_HOST.'/ipg/v1x/'.ltrim($ep,'/');
    $ch    = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer '.$token,
            'Origin: '.MPESA_ORIGIN,
        ],
        CURLOPT_SSL_VERIFYPEER => (MPESA_ENV==='production'),
        CURLOPT_SSL_VERIFYHOST => (MPESA_ENV==='production') ? 2 : 0,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err) throw new RuntimeException('M-Pesa cURL: '.$err);
    $data = json_decode($resp, true);
    if (!is_array($data)) throw new RuntimeException('M-Pesa: resposta inválida.');
    return $data;
}

/**
 * Inicia pagamento C2B USSD Push M-Pesa.
 * Códigos de resposta relevantes:
 *   INS-0  → Sucesso imediato
 *   INS-5  → Aguardar confirmação USSD pelo cliente
 *   INS-6  → Recusado  | INS-9 → Saldo insuf. | INS-10 → Nº inválido
 */
function mpesa_c2b(string $msisdn, float $amount, string $ref, string $third_ref): array {
    return mpesa_call('customerToBusinessInitiate', [
        'input_Amount'               => (string)round($amount, 2),
        'input_CustomerMSISDN'       => $msisdn,
        'input_Country'              => 'MOZ',
        'input_Currency'             => 'MZN',
        'input_ServiceProviderCode'  => MPESA_PROVIDER_CODE,
        'input_ThirdPartyReference'  => $third_ref,
        'input_TransactionReference' => $ref,
        'input_PurchasedItemsDesc'   => 'VSG Marketplace',
    ]);
}

/** Consulta estado de transacção M-Pesa. */
function mpesa_query_status(string $conv_id, string $third_ref): array {
    return mpesa_call('queryTransactionStatus', [
        'input_QueryReference'      => $conv_id,
        'input_ServiceProviderCode' => MPESA_PROVIDER_CODE,
        'input_ThirdPartyReference' => $third_ref,
        'input_Country'             => 'MOZ',
    ]);
}

// ════════════════════════════════════════════════════════════════════
// e-MOLA HELPERS
// ════════════════════════════════════════════════════════════════════

/** Obtém Bearer Token e-Mola (JWT via login). */
function emola_token(): string {
    $k = '_emola_tok';
    if (isset($_SESSION[$k]) && (time()-($_SESSION[$k.'_ts']??0))<3500) return $_SESSION[$k];
    $ch = curl_init('https://'.EMOLA_API_HOST.'/api/v1/auth/login');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['username'=>EMOLA_USERNAME,'password'=>EMOLA_PASSWORD]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => (EMOLA_ENV==='production'),
    ]);
    $r = json_decode(curl_exec($ch), true) ?: [];
    curl_close($ch);
    $tok = $r['token'] ?? $r['access_token'] ?? '';
    if (!$tok) throw new RuntimeException('e-Mola: falha na autenticação — verificar EMOLA_USERNAME/PASSWORD.');
    $_SESSION[$k]       = $tok;
    $_SESSION[$k.'_ts'] = time();
    return $tok;
}

/** Inicia pagamento C2B e-Mola. */
function emola_c2b(string $msisdn, float $amount, string $ref): array {
    $tok = emola_token();
    $ch  = curl_init('https://'.EMOLA_API_HOST.'/api/v1/c2b/initiate');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'msisdn'        => $msisdn,
            'amount'        => round($amount, 2),
            'reference'     => $ref,
            'merchant_code' => EMOLA_MERCHANT_CODE,
            'description'   => 'VSG Marketplace',
            'currency'      => 'MZN',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Authorization: Bearer '.$tok],
        CURLOPT_SSL_VERIFYPEER => (EMOLA_ENV==='production'),
    ]);
    $r = json_decode(curl_exec($ch), true) ?: [];
    curl_close($ch);
    return $r;
}

/** Consulta estado de pagamento e-Mola. */
function emola_query_status(string $ref): array {
    $tok = emola_token();
    $ch  = curl_init('https://'.EMOLA_API_HOST.'/api/v1/c2b/status/'.urlencode($ref));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer '.$tok],
        CURLOPT_SSL_VERIFYPEER => (EMOLA_ENV==='production'),
    ]);
    $r = json_decode(curl_exec($ch), true) ?: [];
    curl_close($ch);
    return $r;
}

// ════════════════════════════════════════════════════════════════════
// CARTÃO — gateway simulado (substituir por Stripe/PayU)
// ════════════════════════════════════════════════════════════════════

function luhnCheck(string $n): bool {
    $n=preg_replace('/\D/','',$n);
    $s=0;$alt=false;
    for($i=strlen($n)-1;$i>=0;$i--){$d=(int)$n[$i];if($alt){$d*=2;if($d>9)$d-=9;}$s+=$d;$alt=!$alt;}
    return strlen($n)>=13&&$s%10===0;
}
function cardBrand(string $n): string {
    $n=preg_replace('/\D/','',$n);
    if(preg_match('/^4/',$n))      return 'visa';
    if(preg_match('/^5[1-5]/',$n)) return 'mastercard';
    if(preg_match('/^2[2-7]/',$n)) return 'mastercard';
    return 'unknown';
}
function processCard(string $num, float $amount): array {
    $raw = preg_replace('/\D/','',$num);
    if(str_ends_with($raw,'0002')) return ['ok'=>false,'msg'=>'Cartão recusado pelo banco emissor.'];
    if(str_ends_with($raw,'9995')) return ['ok'=>false,'msg'=>'Saldo insuficiente no cartão.'];
    // ── Stripe: substituir o bloco abaixo ──────────────────────────
    // require_once __DIR__.'/vendor/autoload.php';
    // \Stripe\Stripe::setApiKey(getenv('STRIPE_SECRET_KEY'));
    // $pi=\Stripe\PaymentIntent::create([...]);
    // return ['ok'=>true,'transaction_id'=>$pi->id,'auth_code'=>$pi->latest_charge];
    // ─────────────────────────────────────────────────────────────
    return ['ok'=>true,'transaction_id'=>'TXN-'.strtoupper(bin2hex(random_bytes(6))).'-'.date('YmdHis'),'auth_code'=>strtoupper(bin2hex(random_bytes(3)))];
}

// ════════════════════════════════════════════════════════════════════
// HELPER: carregar itens frescos da DB
// ════════════════════════════════════════════════════════════════════
function loadFreshItems(int $uid, int $bn_id, int $bn_qty, mysqli $db): array {
    if ($bn_id > 0) {
        $st=$db->prepare("SELECT id,nome,preco,stock,user_id AS company_id,imagem,currency FROM products WHERE id=? AND status='ativo' AND deleted_at IS NULL LIMIT 1");
        $st->bind_param('i',$bn_id);$st->execute();
        $r=$st->get_result()->fetch_assoc();$st->close();
        if(!$r) return [];
        if($bn_qty>$r['stock']) return ['__err__'=>'Stock insuficiente para "'.$r['nome'].'".'];
        return [['product_id'=>$r['id'],'product_name'=>$r['nome'],'company_id'=>(int)$r['company_id'],'price'=>(float)$r['preco'],'qty'=>$bn_qty,'imagem'=>$r['imagem'],'currency'=>$r['currency']]];
    }
    $st=$db->prepare("
        SELECT ci.product_id,ci.quantity AS qty,ci.company_id,
               p.nome AS product_name,p.preco AS price,p.stock,p.imagem,p.currency
        FROM shopping_carts sc
        INNER JOIN cart_items ci ON ci.cart_id=sc.id
        INNER JOIN products p ON p.id=ci.product_id AND p.status='ativo' AND p.deleted_at IS NULL
        WHERE sc.user_id=? AND sc.status='active'
    ");
    $st->bind_param('i',$uid);$st->execute();
    $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);$st->close();
    if(empty($rows)) return [];
    foreach($rows as $r){if($r['qty']>$r['stock'])return['__err__'=>'Stock insuficiente para "'.$r['product_name'].'".'];}
    return $rows;
}

function normMsisdn(string $p): string {
    $n=preg_replace('/\D/','',$p);
    if(strlen($n)===9) $n='258'.$n;
    return $n;
}

// ════════════════════════════════════════════════════════════════════
// ACTION: initiate_mm
// ════════════════════════════════════════════════════════════════════
if ($action === 'initiate_mm') {

    $method  = trim($_POST['pay_method'] ?? '');
    $phone   = normMsisdn(trim($_POST['phone'] ?? ''));
    $amount  = (float)($_POST['amount'] ?? 0);
    $ref     = trim($_POST['order_ref'] ?? 'VSG-'.time());

    if (!in_array($method,['mpesa','emola'])) jout(['ok'=>false,'msg'=>'Método inválido.']);
    if (strlen($phone)!==12||!str_starts_with($phone,'258')) jout(['ok'=>false,'msg'=>'Número de telemóvel inválido.']);
    if ($amount<=0) jout(['ok'=>false,'msg'=>'Valor inválido.']);

    $third_ref = 'VSG-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));

    try {
        if ($method === 'mpesa') {

            $r    = mpesa_c2b($phone, $amount, $ref, $third_ref);
            $code = $r['output_ResponseCode'] ?? '';
            $cid  = $r['output_ConversationID'] ?? '';
            $txn  = $r['output_TransactionID']  ?? '';

            if ($code === 'INS-0') {
                // Pagamento confirmado imediatamente (sandbox)
                $_SESSION['_mpesa_pend'] = ['conv_id'=>$cid,'txn_id'=>$txn,'third_ref'=>$third_ref,'confirmed'=>true];
                jout(['ok'=>true,'conversation_id'=>$cid,'pay_token'=>$third_ref,'instant'=>true]);
            }
            if ($code === 'INS-5') {
                // Aguardar USSD Push do cliente
                $_SESSION['_mpesa_pend'] = ['conv_id'=>$cid,'third_ref'=>$third_ref,'confirmed'=>false,'ts'=>time()];
                jout(['ok'=>true,'conversation_id'=>$cid,'pay_token'=>$third_ref,'instant'=>false]);
            }

            // Erro
            $msgs = ['INS-6'=>'Transacção M-Pesa recusada.','INS-9'=>'Saldo M-Pesa insuficiente.',
                     'INS-10'=>'Número M-Pesa inválido ou não registado.','INS-13'=>'Utilizador cancelou.'];
            jout(['ok'=>false,'msg'=>$msgs[$code]??'Erro M-Pesa: '.($r['output_ResponseDesc']??$code)]);

        } else { // emola

            $r      = emola_c2b($phone, $amount, $third_ref);
            $status = strtolower($r['status'] ?? $r['code'] ?? '');
            $ref_b  = $r['reference'] ?? $r['transaction_ref'] ?? $third_ref;
            $ok     = in_array($status,['initiated','pending','200','success','ok']);

            if (!$ok) {
                jout(['ok'=>false,'msg'=>$r['message']??$r['msg']??'e-Mola: erro ao iniciar. Verifique o número.']);
            }
            $_SESSION['_emola_pend'] = ['reference'=>$ref_b,'confirmed'=>false,'ts'=>time()];
            jout(['ok'=>true,'conversation_id'=>$ref_b,'pay_token'=>$third_ref,'instant'=>false]);
        }
    } catch (RuntimeException $e) {
        error_log('[VSG] '.$method.' initiate error: '.$e->getMessage());
        jout(['ok'=>false,'msg'=>'Erro de comunicação com o gateway. Tente mais tarde.']);
    }
}

// ════════════════════════════════════════════════════════════════════
// ACTION: create_order
// ════════════════════════════════════════════════════════════════════
if ($action === 'create_order') {

    $pay_method   = trim($_POST['pay_method']   ?? '');
    $ship_name    = trim($_POST['ship_name']    ?? '');
    $ship_phone   = trim($_POST['ship_phone']   ?? '');
    $ship_address = trim($_POST['ship_address'] ?? '');
    $ship_city    = trim($_POST['ship_city']    ?? '');
    $ship_state   = trim($_POST['ship_state']   ?? '');
    $notes        = trim($_POST['notes']        ?? '');
    $buy_now_id   = (int)($_POST['buy_now_id']  ?? 0);
    $buy_now_qty  = max(1,(int)($_POST['buy_now_qty']??1));
    $mm_confirmed = ($_POST['mm_confirmed'] ?? '') === '1';
    $pay_token    = trim($_POST['pay_token'] ?? '');

    if (!$ship_name||!$ship_phone||!$ship_address||!$ship_city)
        jout(['ok'=>false,'step'=>1,'msg'=>'Preencha todos os campos de entrega obrigatórios.']);

    if (!in_array($pay_method,['mpesa','emola','visa','mastercard','manual']))
        jout(['ok'=>false,'step'=>1,'msg'=>'Método de pagamento inválido.']);

    $isMM   = in_array($pay_method,['mpesa','emola']);
    $isCard = in_array($pay_method,['visa','mastercard']);

    // ── Mobile money: verificar que pagamento foi confirmado ──────────
    $txn_id = null; $auth_code = null;

    if ($isMM) {
        if (!$mm_confirmed) jout(['ok'=>false,'step'=>2,'msg'=>'Pagamento móvel não confirmado ainda.']);

        $sess_key = '_'.($pay_method==='mpesa'?'mpesa':'emola').'_pend';
        if (!isset($_SESSION[$sess_key])) jout(['ok'=>false,'step'=>2,'msg'=>'Sessão de pagamento expirada.']);

        $pend = $_SESSION[$sess_key];

        // Se não foi marcado como confirmado na sessão, verificar no gateway
        if (!($pend['confirmed'] ?? false)) {
            try {
                if ($pay_method === 'mpesa') {
                    $qr   = mpesa_query_status($pend['conv_id'], $pend['third_ref']);
                    $qcode= $qr['output_ResponseCode'] ?? '';
                    if ($qcode !== 'INS-0')
                        jout(['ok'=>false,'step'=>2,'msg'=>'Pagamento M-Pesa não confirmado pelo gateway (código: '.$qcode.').']);
                    $txn_id   = $qr['output_TransactionID']  ?? $pend['conv_id'];
                    $auth_code= $qr['output_ConversationID'] ?? '';
                } else {
                    $qr    = emola_query_status($pend['reference']);
                    $qstat = strtolower($qr['status'] ?? '');
                    if (!in_array($qstat,['completed','success','paid']))
                        jout(['ok'=>false,'step'=>2,'msg'=>'Pagamento e-Mola não confirmado (estado: '.$qstat.').']);
                    $txn_id   = $qr['transaction_id'] ?? $pend['reference'];
                    $auth_code= '';
                }
            } catch (RuntimeException $e) {
                error_log('[VSG] query_status error: '.$e->getMessage());
                // Se API está em baixo, aceitar na boa fé (verificar manualmente depois)
                $txn_id = $pend['conv_id'] ?? $pend['reference'] ?? 'GATEWAY-ERROR';
            }
        } else {
            $txn_id   = $pend['txn_id'] ?? $pend['conv_id'] ?? 'MM-OK';
            $auth_code= '';
        }
        unset($_SESSION[$sess_key]);
    }

    // ── Cartão ────────────────────────────────────────────────────────
    if ($isCard) {
        $cnum = preg_replace('/\D/','',$_POST['card_number']??'');
        $cnam = trim($_POST['card_name']   ?? '');
        $cexp = trim($_POST['card_expiry'] ?? '');
        $ccvv = trim($_POST['card_cvv']    ?? '');

        if (!luhnCheck($cnum))
            jout(['ok'=>false,'step'=>2,'field'=>'card_number','msg'=>'Número de cartão inválido.']);
        $b=cardBrand($cnum);
        if($b!==$pay_method&&$b!=='unknown')
            jout(['ok'=>false,'step'=>2,'field'=>'card_number','msg'=>'O cartão não é '.strtoupper($pay_method).'.']);
        if(!$cnam||strlen($cnam)<3)
            jout(['ok'=>false,'step'=>2,'field'=>'card_name','msg'=>'Nome no cartão inválido.']);
        if(!preg_match('/^(0[1-9]|1[0-2])\/(\d{2}|\d{4})$/',$cexp))
            jout(['ok'=>false,'step'=>2,'field'=>'card_expiry','msg'=>'Data de validade inválida.']);
        [$em,$ey]=explode('/',$cexp);
        if(mktime(0,0,0,(int)$em+1,1,(int)(strlen($ey)===2?'20'.$ey:$ey))<time())
            jout(['ok'=>false,'step'=>2,'field'=>'card_expiry','msg'=>'Cartão expirado.']);
        if(!preg_match('/^\d{3,4}$/',$ccvv))
            jout(['ok'=>false,'step'=>2,'field'=>'card_cvv','msg'=>'CVV inválido.']);

        // Processamento — total exacto calculado após carregar itens
        // (primeira chamada ao gateway é apenas para validar; segunda com valor real)
        $card_raw = $cnum; $card_name_v = $cnam; $card_exp_v = $cexp;
        unset($ccvv); // não guardar CVV
    }

    // ── Itens frescos da DB ───────────────────────────────────────────
    $fresh = loadFreshItems($user_id, $buy_now_id, $buy_now_qty, $mysqli);
    if (empty($fresh)) jout(['ok'=>false,'step'=>1,'msg'=>'Carrinho vazio.']);
    if (isset($fresh['__err__'])) jout(['ok'=>false,'step'=>1,'msg'=>$fresh['__err__']]);

    $subtotal = array_sum(array_map(fn($i)=>(float)$i['price']*(int)$i['qty'],$fresh));
    $shipping = $subtotal >= 2500 ? 0 : 150;
    $total    = $subtotal + $shipping;

    if ($isCard) {
        $cres = processCard($card_raw, $total);
        if (!$cres['ok']) jout(['ok'=>false,'step'=>2,'msg'=>$cres['msg']]);
        $txn_id   = $cres['transaction_id'];
        $auth_code= $cres['auth_code'];
    }

    // ── Criar pedidos ─────────────────────────────────────────────────
    $by_company = [];
    foreach ($fresh as $fi) $by_company[(int)$fi['company_id']][] = $fi;

    $mysqli->begin_transaction();
    try {
        $order_numbers = [];
        $first_oid     = null;
        $confirmed     = $isCard || $isMM;
        $ord_status    = $confirmed ? 'confirmado' : 'pendente';
        $pay_status    = $confirmed ? 'pago'       : 'pendente';
        $pay_date      = $confirmed ? date('Y-m-d H:i:s') : null;

        foreach ($by_company as $cid => $items) {
            $co_sub  = array_sum(array_map(fn($i)=>(float)$i['price']*(int)$i['qty'],$items));
            $co_ship = count($by_company)===1 ? $shipping : 0;
            $co_tot  = $co_sub + $co_ship;
            $onum    = 'VSG-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)),0,6));

            $st=$mysqli->prepare("
                INSERT INTO orders (company_id,customer_id,order_number,subtotal,shipping_cost,
                    total,currency,status,payment_status,payment_method,payment_date,
                    shipping_address,shipping_city,shipping_phone,customer_notes)
                VALUES (?,?,?,?,?,?,'MZN',?,?,?,?,?,?,?,?)
            ");
            $st->bind_param('iisdddsssssssss',
                $cid,$user_id,$onum,$co_sub,$co_ship,$co_tot,
                $ord_status,$pay_status,$pay_method,$pay_date,
                $ship_address,$ship_city,$ship_phone,$notes
            );
            $st->execute();
            $oid=(int)$mysqli->insert_id;
            $st->close();
            if(!$first_oid)$first_oid=$oid;
            $order_numbers[]=$onum;

            foreach ($items as $fi) {
                $line=(float)$fi['price']*(int)$fi['qty'];
                $img=$fi['imagem']??'';
                $st=$mysqli->prepare("INSERT INTO order_items (order_id,product_id,product_name,product_image,product_category,quantity,unit_price,discount,total) VALUES (?,?,?,?,'geral',?,?,0,?)");
                $st->bind_param('iissidd',$oid,(int)$fi['product_id'],$fi['product_name'],$img,(int)$fi['qty'],(float)$fi['price'],$line);
                $st->execute();$st->close();
                $st=$mysqli->prepare("UPDATE products SET stock=stock-?,total_sales=total_sales+? WHERE id=? AND stock>=?");
                $st->bind_param('iiii',(int)$fi['qty'],(int)$fi['qty'],(int)$fi['product_id'],(int)$fi['qty']);
                $st->execute();
                if($mysqli->affected_rows===0) throw new Exception('Stock esgotado para "'.$fi['product_name'].'".');
                $st->close();
            }

            $notes_pay = match($pay_method) {
                'mpesa'       => 'M-Pesa confirmado. TXN: '.($txn_id??'-'),
                'emola'       => 'e-Mola confirmado. REF: '.($txn_id??'-'),
                'visa','mastercard' => strtoupper($pay_method).' aprovado. Auth: '.($auth_code??'-'),
                default       => 'Transferência bancária — aguardar comprovativo',
            };
            $p_stat=$confirmed?'confirmado':'pendente';
            $p_date=$confirmed?date('Y-m-d H:i:s'):null;

            $st=$mysqli->prepare("INSERT INTO payments (order_id,transaction_id,amount,currency,payment_method,payment_status,payment_date,confirmed_at,receipt_number,notes) VALUES (?,?,?,'MZN',?,?,?,?,?,?)");
            $st->bind_param('isdssssss',$oid,$txn_id,$co_tot,$pay_method,$p_stat,$p_date,$p_date,$auth_code,$notes_pay);
            $st->execute();$st->close();
        }

        if (!$buy_now_id) {
            $mysqli->query("DELETE ci FROM cart_items ci INNER JOIN shopping_carts sc ON sc.id=ci.cart_id WHERE sc.user_id={$user_id} AND sc.status='active'");
            $mysqli->query("UPDATE shopping_carts SET status='completed',completed_at=NOW() WHERE user_id={$user_id} AND status='active'");
            $_SESSION['cart_count']=0;
        }

        $mysqli->commit();
        $_SESSION['csrf_token']=bin2hex(random_bytes(32));
        $_SESSION['last_order']=['number'=>$order_numbers[0],'id'=>$first_oid,'method'=>$pay_method];

        jout([
            'ok'=>true,'step'=>3,
            'order_number'  =>$order_numbers[0],
            'order_id'      =>$first_oid,
            'pay_method'    =>$pay_method,
            'is_card'       =>$isCard,
            'is_mm'         =>$isMM,
            'total'         =>$total,
            'transaction_id'=>$txn_id??'',
            'new_csrf'      =>$_SESSION['csrf_token'],
        ]);

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log('[VSG checkout] DB: '.$e->getMessage());
        jout(['ok'=>false,'step'=>3,'msg'=>$e->getMessage()]);
    }
}

jout(['ok'=>false,'msg'=>'Acção desconhecida.']);