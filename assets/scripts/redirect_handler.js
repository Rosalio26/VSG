/**
 * ================================================================
 * SISTEMA DE REDIRECIONAMENTO INTELIGENTE PARA CADASTRO
 * ================================================================
 * Gerencia redirecionamentos do index.php para o painel de cadastro
 * com o tipo correto (business/pessoa) baseado no botão clicado
 */

(function() {
    'use strict';

    /**
     * Adiciona parâmetro de tipo à URL de cadastro
     */
    function setupRedirectButtons() {
        // Botão "Vender na VSG" (deve ir para business)
        const btnVenderVSG = document.querySelectorAll('a[href*="painel_cadastro.php"]');
        
        btnVenderVSG.forEach(btn => {
            const originalHref = btn.getAttribute('href');
            
            // Verifica se o botão é para vender (business)
            const isBusiness = btn.textContent.toLowerCase().includes('vender') || 
                              btn.querySelector('i.fa-building') !== null;
            
            // Adiciona evento de click para garantir o tipo correto
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                let url = originalHref;
                
                // Remove parâmetros existentes de tipo
                url = url.split('?')[0];
                
                // Adiciona o tipo correto
                if (isBusiness) {
                    url += '?tipo=business';
                    // Salva preferência no localStorage
                    localStorage.setItem('vg_redirect_type', 'business');
                } else {
                    url += '?tipo=pessoal';
                    localStorage.setItem('vg_redirect_type', 'pessoal');
                }
                
                // Redireciona
                window.location.href = url;
            });
        });

        // Botão "Cadastrar-se" no hero (deve ir para pessoa)
        const btnCadastro = document.querySelectorAll('a[href*="painel_cadastro.php"]:not(.nav-link)');
        
        btnCadastro.forEach(btn => {
            const isPerson = btn.textContent.toLowerCase().includes('cadastrar') || 
                           btn.querySelector('i.fa-register') !== null;
            
            if (isPerson && !btn.textContent.toLowerCase().includes('vender')) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    let url = btn.getAttribute('href').split('?')[0];
                    url += '?tipo=pessoal';
                    
                    localStorage.setItem('vg_redirect_type', 'pessoal');
                    window.location.href = url;
                });
            }
        });
    }

    /**
     * Detecta tipo da URL e configura formulário correto
     * Para ser usado no painel_cadastro.php
     */
    function detectTypeFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const tipoURL = urlParams.get('tipo');
        const tipoStorage = localStorage.getItem('vg_redirect_type');
        
        // Prioridade: URL > localStorage > Padrão
        let tipoFinal = tipoURL || tipoStorage || 'pessoal';
        
        // Valida o tipo
        if (tipoFinal !== 'business' && tipoFinal !== 'pessoal') {
            tipoFinal = 'pessoal';
        }
        
        // Limpa o storage após usar
        localStorage.removeItem('vg_redirect_type');
        
        return tipoFinal;
    }

    /**
     * Aplica o tipo correto no painel de cadastro
     */
    function applyTypeOnPanel() {
        // Verifica se estamos no painel de cadastro
        if (!document.getElementById('painel_cadastro')) return;
        
        const tipoDetectado = detectTypeFromURL();
        const tiposPermitidos = JSON.parse(document.body.dataset.tiposPermitidos || '["pessoal"]');
        
        // Verifica se o tipo detectado é permitido
        if (!tiposPermitidos.includes(tipoDetectado)) {
            console.warn(`Tipo ${tipoDetectado} não permitido. Usando padrão.`);
            return;
        }
        
        // Atualiza a sessão via AJAX
        const csrfToken = document.body.dataset.csrf;
        
        fetch('../../registration/ajax/switch-tipo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `tipo=${tipoDetectado}&csrf=${csrfToken}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success || data.tipo) {
                // Força atualização visual
                document.body.setAttribute('data-tipo-inicial', tipoDetectado);
                
                // Atualiza localStorage
                localStorage.setItem('vg_type', tipoDetectado);
                
                // Dispara evento customizado para o sistema de cadastro
                const event = new CustomEvent('tipoAlterado', { 
                    detail: { tipo: tipoDetectado } 
                });
                document.dispatchEvent(event);
                
                console.log(`✅ Tipo aplicado: ${tipoDetectado}`);
            }
        })
        .catch(err => {
            console.error('Erro ao aplicar tipo:', err);
        });
    }

    /**
     * Sistema de alternância dentro do painel
     */
    function setupInternalSwitching() {
        const switchConta = document.getElementById('switchConta');
        if (!switchConta) return;
        
        const buttons = switchConta.querySelectorAll('.btn-toggle');
        const formBusiness = document.getElementById('formBusiness');
        const formPessoa = document.getElementById('formPessoa');
        const titulo = document.getElementById('titulo');
        
        buttons.forEach(btn => {
            btn.addEventListener('click', async function() {
                const novoTipo = this.dataset.tipo;
                const tipoAtual = document.body.getAttribute('data-tipo-inicial');
                
                // Se já está no tipo correto, não faz nada
                if (novoTipo === tipoAtual) return;
                
                // Desabilita botão durante transição
                this.disabled = true;
                
                // Atualiza visualmente
                buttons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Alterna formulários com animação
                if (novoTipo === 'business') {
                    if (formPessoa) formPessoa.hidden = true;
                    if (formBusiness) {
                        formBusiness.hidden = false;
                        formBusiness.style.animation = 'fadeInScale 0.4s ease';
                    }
                    if (titulo) titulo.textContent = 'Cadastro de Negócio';
                    
                    // Alterna imagens de fundo
                    const imgPerson = document.getElementById('img-person-fixa');
                    const imgBusiness = document.getElementById('img-business-fixa');
                    if (imgPerson) imgPerson.classList.remove('active');
                    if (imgBusiness) imgBusiness.classList.add('active');
                } else {
                    if (formBusiness) formBusiness.hidden = true;
                    if (formPessoa) {
                        formPessoa.hidden = false;
                        formPessoa.style.animation = 'fadeInScale 0.4s ease';
                    }
                    if (titulo) titulo.textContent = 'Cadastro Pessoal';
                    
                    // Alterna imagens de fundo
                    const imgPerson = document.getElementById('img-person-fixa');
                    const imgBusiness = document.getElementById('img-business-fixa');
                    if (imgBusiness) imgBusiness.classList.remove('active');
                    if (imgPerson) imgPerson.classList.add('active');
                }
                
                // Atualiza body
                document.body.setAttribute('data-tipo-inicial', novoTipo);
                
                // Salva no localStorage
                localStorage.setItem('vg_type', novoTipo);
                
                // Atualiza sessão
                const csrfToken = document.body.dataset.csrf;
                
                try {
                    const res = await fetch('../../registration/ajax/switch-tipo.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `tipo=${novoTipo}&csrf=${csrfToken}`
                    });
                    
                    const data = await res.json();
                    
                    if (data.error) {
                        console.error('Erro ao alternar:', data.error);
                    }
                } catch (err) {
                    console.error('Erro de conexão:', err);
                } finally {
                    // Reabilita botão
                    setTimeout(() => {
                        this.disabled = false;
                    }, 500);
                }
            });
        });
    }

    /**
     * Adiciona indicador visual no botão ativo
     */
    function highlightActiveType() {
        const tipoAtual = document.body.getAttribute('data-tipo-inicial');
        const buttons = document.querySelectorAll('.btn-toggle');
        
        buttons.forEach(btn => {
            if (btn.dataset.tipo === tipoAtual) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    /**
     * Adiciona toast de boas-vindas baseado no tipo
     */
    function showWelcomeMessage() {
        if (!document.getElementById('painel_cadastro')) return;
        
        const tipoAtual = document.body.getAttribute('data-tipo-inicial');
        
        // Só mostra se veio de um redirect (tem parâmetro na URL)
        const urlParams = new URLSearchParams(window.location.search);
        if (!urlParams.has('tipo')) return;
        
        setTimeout(() => {
            let mensagem = '';
            let icone = '';
            
            if (tipoAtual === 'business') {
                mensagem = 'Bem-vindo! Vamos configurar sua conta empresarial';
                icone = 'fa-building';
            } else {
                mensagem = 'Bem-vindo! Complete seu cadastro pessoal';
                icone = 'fa-user';
            }
            
            // Cria toast
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #00b96b 0%, #059669 100%);
                color: white;
                padding: 16px 20px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.2);
                z-index: 10000;
                font-size: 14px;
                font-weight: 600;
                animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                align-items: center;
                gap: 12px;
            `;
            
            toast.innerHTML = `
                <i class="fa-solid ${icone}" style="font-size: 20px;"></i>
                <span>${mensagem}</span>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }, 500);
    }

    /**
     * Inicialização
     */
    function init() {
        // Se estamos no index.php, configura os botões de redirect
        if (window.location.pathname.includes('index.php') || 
            window.location.pathname === '/' ||
            window.location.pathname.endsWith('/')) {
            setupRedirectButtons();
            console.log('✅ Botões de redirecionamento configurados');
        }
        
        // Se estamos no painel de cadastro, aplica o tipo e configura alternância
        if (document.getElementById('painel_cadastro')) {
            applyTypeOnPanel();
            setupInternalSwitching();
            highlightActiveType();
            showWelcomeMessage();
            console.log('✅ Sistema de alternância configurado');
        }
    }

    // Aguarda DOM estar pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Exporta funções para uso global
    window.RedirectHandler = {
        detectTypeFromURL,
        applyTypeOnPanel,
        setupInternalSwitching
    };

})();