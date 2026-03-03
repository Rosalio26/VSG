<?php
/*
 * ajax_payment_status.php — VSG Marketplace
 * ─────────────────────────────────────────────────────────────────
 * Endpoint de polling — chamado pelo frontend a cada 5 segundos
 * para verificar se o cliente confirmou o pagamento no telemóvel.
 *
 * Colocar em: pages/app/ajax/ajax_payment_status.php
 *
 * Parâmetros GET:
 *   conv_id  → conversation ID (M-Pesa) ou reference (e-Mola)
 *   method   → 'mpesa' ou 'emola'
 *   token    → third_party_ref gerado no initiate_mm
 *
 * Respostas:
 *   { status: 'pending'   }  → aguardar (continuar polling)
 *   { status: 'confirmed', transaction_id: '...' }  → pagamento OK
 *   { status: 'failed',    msg: '...' }  → pagamento falhado/cancelado
 * ─────────────────────────────────────────────────────────────────
 */

ob_start();
set_error_handler(function(int $no, string $str, string $file, int $line): bool {
    ob_clean(); header('Content-Type: application/json; charset=utf-8');
    $dev=(getenv('APP_ENV')==='development');
    echo json_encode(['status'=>'failed','msg'=>$dev?"PHP[{$no}]: {$str} em ".basename($file).":{$line}":'Erro interno.'],JSON_UNESCAPED_UNICODE); exit;
});
set_exception_handler(function(Throwable $e): void {
    ob_clean(); header('Content-Type: application/json; charset=utf-8');
    $dev=(getenv('APP_ENV')==='development');
    echo json_encode(['status'=>'failed','msg'=>$dev?get_class($e).': '.$e->getMessage():'Erro interno.'],JSON_UNESCAPED_UNICODE); exit;
});

require_once __DIR__ . '/../registration/bootstrap.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function jout(array $d): never {
    if(ob_get_level()){$l=ob_get_clean();if(trim($l))error_log('[VSG poll] leak: '.substr($l,0,200));}
    echo json_encode($d, JSON_UNESCAPED_UNICODE); exit;
}

// ── Auth ──────────────────────────────────────────────────────────
if (empty($_SESSION['auth']['user_id'])) {
    jout(['status'=>'failed','msg'=>'Sessão expirada.']);
}

// ── Só GET com XHR ───────────────────────────────────────────────
if (($_SERVER['HTTP_X_REQUESTED_WITH']??'') !== 'XMLHttpRequest') {
    http_response_code(403); jout(['status'=>'failed','msg'=>'Proibido.']);
}

$conv_id = trim($_GET['conv_id'] ?? '');
$method  = trim($_GET['method']  ?? '');
$token   = trim($_GET['token']   ?? '');

if (!$conv_id || !in_array($method,['mpesa','emola'])) {
    jout(['status'=>'failed','msg'=>'Parâmetros inválidos.']);
}

// Verificar se a sessão de pagamento ainda existe e não expirou (5 min)
$sess_key = '_'.$method.'_pend';
if (!isset($_SESSION[$sess_key])) {
    jout(['status'=>'failed','msg'=>'Sessão de pagamento expirada.']);
}
$pend = $_SESSION[$sess_key];
if ((time() - ($pend['ts']??0)) > 300) {
    unset($_SESSION[$sess_key]);
    jout(['status'=>'failed','msg'=>'Tempo limite excedido.']);
}

// Se já estava confirmada na sessão (ex: sandbox INS-0 imediato)
if ($pend['confirmed'] ?? false) {
    jout(['status'=>'confirmed','transaction_id'=>$pend['txn_id']??$conv_id]);
}

// ── Configuração ─────────────────────────────────────────────────
define('MPESA_ENV',           getenv('MPESA_ENV')           ?: 'sandbox');
define('MPESA_API_KEY',       getenv('MPESA_API_KEY')       ?: 'SUA_API_KEY_AQUI');
define('MPESA_PUBLIC_KEY',    getenv('MPESA_PUBLIC_KEY')    ?: 'SUA_PUBLIC_KEY_AQUI');
define('MPESA_PROVIDER_CODE', getenv('MPESA_PROVIDER_CODE') ?: '171717');
define('MPESA_ORIGIN',        getenv('MPESA_ORIGIN')        ?: 'vsgmarket.co.mz');
define('MPESA_API_HOST', MPESA_ENV==='production' ? 'api.vm.co.mz' : 'api.sandbox.vm.co.mz');
define('EMOLA_ENV',           getenv('EMOLA_ENV')           ?: 'sandbox');
define('EMOLA_USERNAME',      getenv('EMOLA_USERNAME')      ?: '');
define('EMOLA_PASSWORD',      getenv('EMOLA_PASSWORD')      ?: '');
define('EMOLA_MERCHANT_CODE', getenv('EMOLA_MERCHANT_CODE') ?: '');
define('EMOLA_API_HOST', EMOLA_ENV==='production' ? 'emola.movitel.co.mz' : 'emola-sandbox.movitel.co.mz');

// ── M-Pesa helpers ───────────────────────────────────────────────
function mpesa_bearer(): string {
    if (isset($_SESSION['_mpesa_tok'])&&(time()-($_SESSION['_mpesa_tok_ts']??0))<3500)
        return $_SESSION['_mpesa_tok'];
    $pem="-----BEGIN PUBLIC KEY-----\n".chunk_split(MPESA_PUBLIC_KEY,60,"\n")."-----END PUBLIC KEY-----";
    $k=openssl_get_publickey($pem);
    if(!$k) throw new RuntimeException('Chave pública M-Pesa inválida.');
    openssl_public_encrypt(MPESA_API_KEY,$enc,$k,OPENSSL_PKCS1_PADDING);
    $tok=base64_encode($enc);
    $_SESSION['_mpesa_tok']=$tok;$_SESSION['_mpesa_tok_ts']=time();
    return $tok;
}
function mpesa_query_tx(string $conv_id, string $third_ref): array {
    $tok=mpesa_bearer();
    $url='https://'.MPESA_API_HOST.'/ipg/v1x/queryTransactionStatus';
    $ch=curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode([
            'input_QueryReference'      =>$conv_id,
            'input_ServiceProviderCode' =>MPESA_PROVIDER_CODE,
            'input_ThirdPartyReference' =>$third_ref,
            'input_Country'             =>'MOZ',
        ]),
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$tok,'Origin: '.MPESA_ORIGIN],
        CURLOPT_SSL_VERIFYPEER=>(MPESA_ENV==='production'),
        CURLOPT_SSL_VERIFYHOST=>(MPESA_ENV==='production')?2:0,
    ]);
    $r=json_decode(curl_exec($ch),true)?:[];curl_close($ch);
    return $r;
}

// ── e-Mola helpers ───────────────────────────────────────────────
function emola_token(): string {
    $k='_emola_tok';
    if(isset($_SESSION[$k])&&(time()-($_SESSION[$k.'_ts']??0))<3500)return $_SESSION[$k];
    $ch=curl_init('https://'.EMOLA_API_HOST.'/api/v1/auth/login');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['username'=>EMOLA_USERNAME,'password'=>EMOLA_PASSWORD]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_SSL_VERIFYPEER=>(EMOLA_ENV==='production')]);
    $r=json_decode(curl_exec($ch),true)?:[];curl_close($ch);
    $tok=$r['token']??$r['access_token']??'';
    if(!$tok)throw new RuntimeException('e-Mola auth failed.');
    $_SESSION[$k]=$tok;$_SESSION[$k.'_ts']=time();return $tok;
}
function emola_query_tx(string $ref): array {
    $tok=emola_token();
    $ch=curl_init('https://'.EMOLA_API_HOST.'/api/v1/c2b/status/'.urlencode($ref));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$tok],CURLOPT_SSL_VERIFYPEER=>(EMOLA_ENV==='production')]);
    $r=json_decode(curl_exec($ch),true)?:[];curl_close($ch);
    return $r;
}

// ── Consultar gateway ─────────────────────────────────────────────
try {
    if ($method === 'mpesa') {

        $qr    = mpesa_query_tx($conv_id, $pend['third_ref'] ?? $token);
        $code  = $qr['output_ResponseCode'] ?? '';
        $txn   = $qr['output_TransactionID'] ?? '';

        if ($code === 'INS-0') {
            // Pagamento confirmado
            $_SESSION[$sess_key]['confirmed'] = true;
            $_SESSION[$sess_key]['txn_id']    = $txn;
            jout(['status'=>'confirmed','transaction_id'=>$txn]);
        }
        if (in_array($code,['INS-6','INS-9','INS-10','INS-13'])) {
            $msgs=['INS-6'=>'Recusado.','INS-9'=>'Saldo insuficiente.','INS-10'=>'Número inválido.','INS-13'=>'Cancelado pelo utilizador.'];
            unset($_SESSION[$sess_key]);
            jout(['status'=>'failed','msg'=>'M-Pesa: '.($msgs[$code]??$code)]);
        }
        // INS-5 ou outro código transitório → ainda pendente
        jout(['status'=>'pending']);

    } else { // emola

        $qr     = emola_query_tx($pend['reference'] ?? $conv_id);
        $status = strtolower($qr['status'] ?? '');

        if (in_array($status,['completed','success','paid'])) {
            $txn = $qr['transaction_id'] ?? $conv_id;
            $_SESSION[$sess_key]['confirmed'] = true;
            $_SESSION[$sess_key]['txn_id']    = $txn;
            jout(['status'=>'confirmed','transaction_id'=>$txn]);
        }
        if (in_array($status,['failed','cancelled','rejected'])) {
            unset($_SESSION[$sess_key]);
            jout(['status'=>'failed','msg'=>'e-Mola: '.($qr['message']??'Pagamento recusado.')]);
        }
        // pending / initiated → continuar polling
        jout(['status'=>'pending']);
    }

} catch (RuntimeException $e) {
    error_log('[VSG] payment_status poll error: '.$e->getMessage());
    // Em caso de erro de rede, não falhar — continuar a tentar
    jout(['status'=>'pending']);
}