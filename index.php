<!DOCTYPE html>
<html lang="pt-BR">
  <head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="VSG Marketplace — Produtos sustentáveis e eco-friendly verificados. Conectamos compradores conscientes a fornecedores certificados.">
  <title>VSG Marketplace — Sustentabilidade que Transforma</title>

  <!-- Fontes: Sora (títulos) + DM Sans (corpo) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,300&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">

  <!-- Fontes: internos do sistema -->
   <link rel="stylesheet" href="./assets/style/index/style.css">

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>

  </head>
  <body>

    <!-- ══ LOADER ═════════════════════════════════════════════ -->
    <div id="vsg-loader" style="display: none;">
      <div class="ld-logo">VSG<span>•</span>Marketplace</div>
      <div class="ld-track"><div class="ld-bar"></div></div>
    </div>

    <!-- ══ ANNOUNCEMENT BAR ═══════════════════════════════════ -->
    <div class="ann" id="ann-bar" style="display: none;">
      <div class="ann-badge"><span class="ann-dot"></span>Novo</div>
      <span>Já temos <span id="ann-count">—</span> produtos eco-certificados disponíveis hoje</span>
      <button class="ann-x" id="ann-close" aria-label="Fechar">
        <svg data-lucide="x"></svg>
      </button>
    </div>

    <!-- ══ NAVBAR ══════════════════════════════════════════════ -->
    <nav class="navbar">
      <div class="nav-in">
        <a href="index.html" class="nav-logo">VSG<em>•</em></a>
        <ul class="nav-links">
          <li><a href="#estrategias"><svg data-lucide="lightbulb"></svg>Estratégias</a></li>
          <li><a href="#como-funciona"><svg data-lucide="git-merge"></svg>Como Funciona</a></li>
          <li><a href="#impacto"><svg data-lucide="globe-2"></svg>Impacto</a></li>
          <li><a href="shopping.php"><svg data-lucide="shopping-bag"></svg>Shopping</a></li>
        </ul>
        <div class="nav-r">
          <a href="registration/login/login.php" class="btn-outline">
            <svg data-lucide="lock"></svg>Entrar
          </a>
          <a href="registration/register/painel_cadastro.php" class="btn-solid">
            <svg data-lucide="user"></svg>Cadastrar-se
          </a>
        </div>
      </div>
    </nav>

    <!-- ══ HERO ════════════════════════════════════════════════ -->
    <section class="hero">
      <?php include "includes/index/hero.html"; ?>
    </section>
    
    <!-- ══ TICKER ══════════════════════════════════════════════ -->
    <div class="ticker">
      <?php include "includes/index/ticker.html"; ?>
    </div>
    
    <!-- ══ SAFE BAR ════════════════════════════════════════════ -->
    <section class="sec-mid">
      <?php include "includes/index/safe_bar.html"; ?>
    </section>

    <!-- ══ ESTRATÉGIAS ═════════════════════════════════════════ -->
    <section class="sec" id="estrategias">
      <?php include "includes/index/estrategias.html"; ?>
    </section>
    
    <!-- ══ COMO FUNCIONA ═══════════════════════════════════════ -->
    <section class="sec-mid" id="como-funciona">
      <?php include "includes/index/funcionamento.html"; ?>
    </section>
    
    <!-- ══ IMPACTO GLOBAL (STATS) ══════════════════════════════ -->
    <section class="sec-dark" id="impacto">
      <?php include "includes/index/impacto.html"; ?>
    </section>
    
    <!-- ══ CTA FINAL ════════════════════════════════════════════ -->
    <section class="sec">
      <div class="wrap">
        <div class="cta-box rv">
          <div class="cta-inner">
            <h2 class="cta-h">Pronto para fazer parte<br>da <span class="gr">mudança</span>?</h2>
            <p class="cta-p">Junte-se a compradores e fornecedores que já escolheram a sustentabilidade.</p>
            <div class="cta-btns">
              <a href="shopping.php" class="btn-cta p">
                <svg data-lucide="shopping-bag"></svg>Ir ao Shopping<svg data-lucide="arrow-right"></svg>
              </a>
              <a href="registration/register/painel_cadastro.php" class="btn-cta s">
                <svg data-lucide="building-2"></svg>Cadastrar como Fornecedor
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ══ FOOTER ═══════════════════════════════════════════════ -->
    <footer>
      <?php include "includes/index/footer.html"; ?>
    </footer>

    <button id="btt" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Topo">
      <svg data-lucide="arrow-up"></svg>
    </button>

    <!-- ══════════════════════════════════════════════════════════
        JS — completamente inline, zero dependências externas
        (Lucide já carregado no <head> via unpkg)
    ══════════════════════════════════════════════════════════ -->
    <script>
    /* ── 1. Inicializar ícones Lucide ──────────────────────── */
    document.addEventListener('DOMContentLoaded', () => lucide.createIcons());

    /* ── 2. Loader ─────────────────────────────────────────── */
    window.addEventListener('load', () => {
      setTimeout(() => document.getElementById('vsg-loader').classList.add('out'), 800);
    });

    /* ── 3. Ano no footer ──────────────────────────────────── */
    document.getElementById('yr').textContent = new Date().getFullYear();

    /* ── 4. Fechar barra de anúncio ────────────────────────── */
    document.getElementById('ann-close').addEventListener('click', () => {
      document.getElementById('ann-bar').style.display = 'none';
    });

    /* ── 5. Back-to-top ─────────────────────────────────────── */
    const btt = document.getElementById('btt');
    window.addEventListener('scroll', () => btt.classList.toggle('on', scrollY > 480), {passive: true});

    /* ── 6. Scroll reveal com stagger ──────────────────────── */
    new IntersectionObserver((entries, obs) => {
      let i = 0;
      entries.forEach(e => {
        if (e.isIntersecting) {
          setTimeout(() => e.target.classList.add('vis'), i++ * 65);
          obs.unobserve(e.target);
        }
      });
    }, { threshold: .08 })
    .observe = (() => {
      const io = new IntersectionObserver((entries, obs) => {
        let i = 0;
        entries.forEach(e => {
          if (e.isIntersecting) {
            setTimeout(() => e.target.classList.add('vis'), i++ * 65);
            obs.unobserve(e.target);
          }
        });
      }, { threshold: .08 });
      document.querySelectorAll('.rv, .rvl').forEach(el => io.observe(el));
      return io.observe.bind(io);
    })();

    /* ── 7. Scroll suave para âncoras ───────────────────────── */
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const t = document.querySelector(a.getAttribute('href'));
        if (t) { e.preventDefault(); t.scrollIntoView({behavior: 'smooth', block: 'start'}); }
      });
    });

    /* ── 8. Newsletter ──────────────────────────────────────── */
    function sendNl(e) {
      e.preventDefault();
      const btn = e.target.querySelector('.nl-b');
      btn.innerHTML = '<svg data-lucide="check"></svg>Enviado!';
      btn.style.background = '#00a85e';
      lucide.createIcons();
      e.target.querySelector('.nl-i').value = '';
      setTimeout(() => {
        btn.innerHTML = '<svg data-lucide="send"></svg>Enviar';
        btn.style.background = '';
        lucide.createIcons();
      }, 3200);
    }

    /* ── 9. Animação de contador ─────────────────────────────────────
      @param el      — elemento DOM alvo
      @param target  — valor final numérico
      @param cap     — se target >= cap exibe "+cap" (regra +9999)
      @param float   — true = 1 casa decimal
    ──────────────────────────────────────────────────────────────── */
    function countUp(el, target, cap, float) {
      el.classList.remove('dim');
      if (cap != null && target >= cap) {
        el.textContent = '+' + Number(cap).toLocaleString('pt-BR');
        return;
      }
      const STEPS = 55, MS = 1600;
      let cur = 0;
      const inc = target / STEPS;
      const t = setInterval(() => {
        cur += inc;
        if (cur >= target) { cur = target; clearInterval(t); }
        el.textContent = float ? cur.toFixed(1) : Math.floor(cur).toLocaleString('pt-BR');
      }, MS / STEPS);
    }

    /* ── 10. Fetch api/stats.php ──────────────────────────────────────
      Retorna: { total_products, total_suppliers, total_countries, avg_rating }
      Em caso de falha mantém "—" sem nenhum erro visível.
    ──────────────────────────────────────────────────────────────── */
    let fetched = false;
    async function loadStats() {
      if (fetched) return;
      fetched = true;
      try {
        const res = await fetch('api/stats.php');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const raw = await res.text();
        if (!raw.trim().startsWith('{')) throw new Error('Não é JSON');
        const d = JSON.parse(raw);
        if (d.error) throw new Error('API erro');

        const cap = parseInt(document.getElementById('stat-prod').dataset.cap) || null;

        /* Secção Impacto */
        countUp(document.getElementById('stat-prod'),  d.total_products,  cap,   false);
        countUp(document.getElementById('stat-sup'),   d.total_suppliers, null,  false);
        countUp(document.getElementById('stat-ctry'),  d.total_countries, null,  false);

        /* Rating */
        const elR = document.getElementById('stat-rat');
        elR.classList.remove('dim');
        const rat = parseFloat(d.avg_rating) || 0;
        if (rat > 0) {
          let c = 0; const st = 40; const inc2 = rat / st;
          const tt = setInterval(() => {
            c += inc2; if (c >= rat) { c = rat; clearInterval(tt); }
            elR.textContent = c.toFixed(1) + ' ★';
          }, 1600 / st);
        } else { elR.textContent = '— ★'; }

        /* Hero inline */
        const hP = document.getElementById('h-prod');
        const hS = document.getElementById('h-sup');
        const hC = document.getElementById('h-ctry');
        const hR = document.getElementById('h-rat');
        if (hP) countUp(hP, d.total_products,  cap,  false);
        if (hS) countUp(hS, d.total_suppliers, null, false);
        if (hC) countUp(hC, d.total_countries, null, false);
        if (hR) hR.textContent = rat > 0 ? rat.toFixed(1) + ' ★' : '—';

        /* Announcement bar */
        const ann = document.getElementById('ann-count');
        if (ann) ann.textContent = (cap != null && d.total_products >= cap)
          ? '+' + Number(cap).toLocaleString('pt-BR')
          : d.total_products.toLocaleString('pt-BR');

      } catch(err) {
        console.warn('[VSG]', err.message); /* falha silenciosa */
      }
    }

    /* Carrega imediatamente (hero e announcement precisam dos dados) */
    loadStats();

    /* Também dispara quando a secção de impacto entra na viewport */
    const impEl = document.getElementById('impacto');
    if (impEl) {
      const ioS = new IntersectionObserver(en => {
        if (en[0].isIntersecting) { loadStats(); ioS.disconnect(); }
      }, { threshold: .08 });
      ioS.observe(impEl);
    }

    /* ── Produto em destaque — api/featured-product.php ─────────────
      Preenche o hero card com o produto mais vendido do banco.
      Actualiza: categoria, rating, imagem, certificações, nome, preço.
      Falha silenciosa: se a API não responder o card mostra "—".
    ──────────────────────────────────────────────────────────────── */
    async function loadFeaturedProduct() {
      try {
        const res = await fetch('api/featured-product.php');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const raw = await res.text();
        if (!raw.trim().startsWith('{')) throw new Error('Não é JSON');
        const d = JSON.parse(raw);
        if (d.error) throw new Error('API erro');

        /* Categoria */
        document.getElementById('fp-cat').innerHTML =
          `<svg data-lucide="leaf"></svg>${d.category_name || 'Produto'}`;

        /* Rating */
        const rat = parseFloat(d.avg_rating) || 0;
        document.getElementById('fp-stars').innerHTML =
          `<svg data-lucide="star"></svg>${rat.toFixed(1)}
          <span style="color:var(--t3)">(${d.review_count})</span>`;

        /* Imagem ou emoji */
        const vis = document.getElementById('fp-visual');
        if (d.imagem) {
          vis.style.padding  = '0';
          vis.style.fontSize = '0';
          vis.innerHTML =
            `<img src="pages/uploads/products/${d.imagem}" alt="${d.nome}"
                  style="width:100%;height:100%;object-fit:cover;
                        position:absolute;inset:0;border-radius:0;">`;
          vis.style.position = 'relative';
        } else {
          vis.textContent = d.category_icon || '🌿';
        }

        /* Certificações / fornecedor */
        document.getElementById('fp-sup').textContent =
          d.certifications || d.company_name || '';

        /* Nome */
        document.getElementById('fp-name').textContent = d.nome;

        /* Preço */
        document.getElementById('fp-price').innerHTML =
          `${parseFloat(d.preco).toLocaleString('pt-BR')} <small>${d.currency || 'MZN'}</small>`;

        /* Link comprar */
        document.getElementById('fp-link').href = `shopping.php?product=${d.id}`;

        /* Re-renderizar ícones Lucide nos novos SVGs */
        lucide.createIcons();

      } catch (err) {
        console.warn('[VSG featured]', err.message);
        /* Sem produto em destaque — mostrar bloco CTA de vendedor */
        const card = document.querySelector('.hero-card');
        const cta  = document.getElementById('seller-cta');
        if (card) card.style.display = 'none';
        if (cta)  { cta.style.display = 'flex'; lucide.createIcons(); }
      }
    }

    loadFeaturedProduct();
    </script>
  </body>
</html>