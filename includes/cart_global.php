<?php
/*
 * includes/cart_global.php — VSG Marketplace
 *
 * Inclua este ficheiro em qualquer página que tenha botões "Adicionar ao Carrinho".
 * Compatível com visitantes (localStorage) e utilizadores autenticados (BD).
 *
 * Uso no PHP:
 *   <?php include __DIR__ . '/includes/cart_global.php'; ?>
 *
 * Uso no HTML (botão):
 *   <button onclick="CartGlobal.add(<?= $product['id'] ?>, 1, <?= json_encode([
 *       'name'     => $product['nome'],
 *       'price'    => $product['preco'],
 *       'img'      => $img_url,
 *       'stock'    => $product['stock'],
 *       'category' => $category_name,
 *       'company'  => $company_name,
 *   ]) ?>, this)">
 *     Adicionar ao Carrinho
 *   </button>
 */

$_cart_logged = isset($_SESSION['auth']['user_id']);
$_cart_uid    = $_cart_logged ? (int)$_SESSION['auth']['user_id'] : 0;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Contar badge para utilizadores logados
$_cart_count = 0;
if ($_cart_logged) {
    $st = $mysqli->prepare("
        SELECT COALESCE(SUM(ci.quantity),0)
        FROM shopping_carts sc
        INNER JOIN cart_items ci ON ci.cart_id = sc.id
        WHERE sc.user_id = ? AND sc.status = 'active'
    ");
    $st->bind_param('i', $_cart_uid);
    $st->execute();
    $_cart_count = (int)$st->get_result()->fetch_row()[0];
    $st->close();
}
?>
<script>
/* ══════════════════════════════════════════════════════════════════
   CartGlobal — API de adicionar ao carrinho para TODAS as páginas
   Visitante  → localStorage (persiste, não some no refresh)
   Autenticado → AJAX → base de dados
══════════════════════════════════════════════════════════════════ */
(function(){
'use strict';

const IS_LOGGED = <?= $_cart_logged ? 'true' : 'false' ?>;
const CSRF      = <?= json_encode($_SESSION['csrf_token']) ?>;
const LS_KEY    = 'vsg_cart_v2';

/* ── Actualiza todos os badges do carrinho na página ── */
function syncBadges(count) {
    document.querySelectorAll('.cart-badge, [data-cart-count]').forEach(el => {
        el.textContent = count > 99 ? '99+' : count;
        if(count > 0) el.style.display = '';
    });
}

/* ── Badge inicial ── */
(function initBadge(){
    if(IS_LOGGED) {
        syncBadges(<?= $_cart_count ?>);
    } else {
        try {
            const d = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
            const n = Object.values(d).reduce((a,i)=>a+(i.qty||0),0);
            syncBadges(n);
        } catch(e){}
    }
})();

/* ── Feedback visual no botão ── */
function setBtnState(btn, state) {
    if(!btn) return;
    if(state === 'loading') {
        btn._orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';
    } else if(state === 'success') {
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Adicionado!';
        btn.style.background = '#00b96b';
        btn.style.color = '#fff';
        setTimeout(()=>{
            btn.innerHTML  = btn._orig || btn.innerHTML;
            btn.disabled   = false;
            btn.style.background = '';
            btn.style.color = '';
        }, 1800);
    } else if(state === 'error') {
        btn.innerHTML = btn._orig || btn.innerHTML;
        btn.disabled  = false;
    }
}

/* ── Toast flutuante ── */
function toast(msg, type='success') {
    document.querySelectorAll('.vsg-toast').forEach(t=>t.remove());
    const icons={success:'check-circle',error:'exclamation-circle',info:'info-circle'};
    const el = document.createElement('div');
    el.className = 'vsg-toast vsg-toast-'+type;
    el.style.cssText = `
        position:fixed;bottom:24px;right:20px;z-index:9999;
        display:flex;align-items:center;gap:10px;
        padding:12px 20px;border-radius:10px;
        box-shadow:0 4px 16px rgba(0,0,0,.15);
        font-family:'Plus Jakarta Sans',sans-serif;font-size:13.5px;font-weight:600;
        animation:toastIn .3s cubic-bezier(.16,1,.3,1);
        ${type==='success'?'background:#f0fdf4;color:#166534;border:1px solid #bbf7d0':
          type==='error'  ?'background:#fef2f2;color:#991b1b;border:1px solid #fecaca':
                           'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe'}
    `;
    el.innerHTML = `<i class="fa-solid fa-${icons[type]||'info-circle'}"></i><span>${msg}</span>`;
    if(!document.querySelector('#vsg-toast-style')){
        const s=document.createElement('style');
        s.id='vsg-toast-style';
        s.textContent='@keyframes toastIn{from{transform:translateY(60px);opacity:0}to{transform:none;opacity:1}}';
        document.head.appendChild(s);
    }
    document.body.appendChild(el);
    setTimeout(()=>{ el.style.opacity='0'; el.style.transition='opacity .3s'; setTimeout(()=>el.remove(),350); }, 3000);
}

/* ══════════════════════════════════════
   API PÚBLICA
══════════════════════════════════════ */
window.CartGlobal = {

    /**
     * Adiciona produto ao carrinho.
     *
     * @param {number} pid      - ID do produto
     * @param {number} qty      - Quantidade (default 1)
     * @param {object} meta     - { name, price, img, stock, category, company }
     * @param {Element} btn     - Botão que disparou a acção (para feedback visual)
     */
    add(pid, qty, meta, btn) {
        pid = parseInt(pid);
        qty = parseInt(qty) || 1;
        if(!pid) return;

        setBtnState(btn, 'loading');

        if(IS_LOGGED) {
            fetch('ajax/ajax_cart.php', {
                method: 'POST',
                headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
                body: `action=add&product_id=${pid}&quantity=${qty}&csrf_token=${CSRF}`
            })
            .then(r=>r.json())
            .then(d=>{
                if(d.success) {
                    setBtnState(btn, 'success');
                    if(d.cart_count !== undefined) syncBadges(d.cart_count);
                    toast('Produto adicionado ao carrinho!');
                } else {
                    setBtnState(btn, 'error');
                    toast(d.message || 'Não foi possível adicionar.', 'error');
                }
            })
            .catch(()=>{ setBtnState(btn,'error'); toast('Erro de conexão.','error'); });

        } else {
            // Visitante → localStorage
            try {
                const d   = JSON.parse(localStorage.getItem(LS_KEY) || '{}');
                const cur = d[pid] || {qty:0};
                const max = meta?.stock || 9999;
                const newQty = Math.min((cur.qty||0)+qty, max);
                d[pid] = {
                    qty      : newQty,
                    name     : meta?.name     || '',
                    price    : meta?.price    || 0,
                    img      : meta?.img      || '',
                    stock    : max,
                    category : meta?.category || 'Geral',
                    company  : meta?.company  || '',
                };
                localStorage.setItem(LS_KEY, JSON.stringify(d));

                const total = Object.values(d).reduce((a,i)=>a+(i.qty||0),0);
                syncBadges(total);
                setBtnState(btn,'success');
                toast('Produto adicionado ao carrinho!');
            } catch(e) {
                setBtnState(btn,'error');
                toast('Não foi possível adicionar.','error');
            }
        }
    },

    /** Devolve a contagem actual do carrinho */
    count() {
        if(IS_LOGGED) {
            return parseInt(document.querySelector('.cart-badge')?.textContent) || 0;
        }
        try {
            const d = JSON.parse(localStorage.getItem(LS_KEY)||'{}');
            return Object.values(d).reduce((a,i)=>a+(i.qty||0),0);
        } catch(e){ return 0; }
    }
};

})();
</script>