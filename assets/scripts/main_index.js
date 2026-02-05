/**
 * VSG Marketplace - Main JavaScript
 * Sistema principal de interatividade
 */

(function() {
    'use strict';
    
    // =================================================================
    // BUSCA AJAX
    // =================================================================
    
    const SearchSystem = {
        init() {
            this.searchInput = document.getElementById('searchInput');
            this.searchBtn = document.getElementById('searchBtn');
            this.clearBtn = document.getElementById('clearSearchBtn');
            this.searchResults = document.getElementById('searchResults');
            this.searchOverlay = document.getElementById('searchOverlay');
            this.searchTimeout = null;
            this.currentSearchTerm = '';
            
            this.bindEvents();
        },
        
        bindEvents() {
            if (!this.searchInput) return;
            
            // Input com debounce
            this.searchInput.addEventListener('input', (e) => {
                const term = e.target.value.trim();
                this.currentSearchTerm = term;
                
                // Mostrar/esconder botão limpar
                if (this.clearBtn) {
                    this.clearBtn.style.display = term ? 'block' : 'none';
                }
                
                // Debounce
                clearTimeout(this.searchTimeout);
                
                if (term.length >= 2) {
                    this.searchTimeout = setTimeout(() => {
                        this.performSearch(term);
                    }, 500);
                } else {
                    this.hideResults();
                }
            });
            
            // Enter key
            this.searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const term = this.searchInput.value.trim();
                    if (term.length >= 2) {
                        this.performSearch(term);
                    }
                }
            });
            
            // Botão de busca
            if (this.searchBtn) {
                this.searchBtn.addEventListener('click', () => {
                    const term = this.searchInput.value.trim();
                    if (term.length >= 2) {
                        this.performSearch(term);
                    } else if (term.length === 0) {
                        this.showAlert('Digite algo para buscar');
                        this.searchInput.focus();
                    } else {
                        this.showAlert('Digite pelo menos 2 caracteres');
                        this.searchInput.focus();
                    }
                });
            }
            
            // Botão limpar
            if (this.clearBtn) {
                this.clearBtn.addEventListener('click', () => {
                    this.searchInput.value = '';
                    this.currentSearchTerm = '';
                    this.clearBtn.style.display = 'none';
                    this.hideResults();
                    this.searchInput.focus();
                });
            }
            
            // Overlay
            if (this.searchOverlay) {
                this.searchOverlay.addEventListener('click', () => this.hideResults());
            }
            
            // ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.searchResults && this.searchResults.style.display === 'block') {
                    this.hideResults();
                }
            });
            
            // Click fora
            document.addEventListener('click', (e) => {
                if (this.searchInput && !this.searchInput.contains(e.target) && 
                    this.searchResults && !this.searchResults.contains(e.target) && 
                    this.searchBtn && !this.searchBtn.contains(e.target)) {
                    this.hideResults();
                }
            });
        },
        
        async performSearch(term) {
            if (!term || term.length < 2) {
                this.hideResults();
                return;
            }
            
            this.showLoading();
            
            try {
                const response = await fetch(`pages/app/ajax/ajax_search.php?search=${encodeURIComponent(term)}&limit=12`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    this.displayResults(data);
                } else {
                    this.displayNoResults(data.message || 'Nenhum produto encontrado');
                }
            } catch (error) {
                console.error('Erro na busca:', error);
                this.displayNoResults('Erro ao buscar produtos. Tente novamente.');
            }
        },
        
        showLoading() {
            if (!this.searchResults) return;
            
            this.searchResults.innerHTML = `
                <div class="search-loading">
                    <div class="spinner-small"></div>
                    <p>Buscando produtos...</p>
                </div>
            `;
            this.searchResults.style.display = 'block';
            
            if (this.searchOverlay) {
                this.searchOverlay.classList.add('active');
            }
        },
        
        displayResults(data) {
            if (!this.searchResults) return;
            
            if (!data.products || data.products.length === 0) {
                this.displayNoResults('Nenhum produto encontrado');
                return;
            }
            
            let html = `
                <div class="search-results-header">
                    <h3>${this.escapeHtml(data.message || 'Resultados da busca')}</h3>
                    <a href="pages/app/search_products.php?search=${encodeURIComponent(this.currentSearchTerm)}" class="view-all-link">
                        Ver todos <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <div class="search-results-grid">
            `;
            
            data.products.forEach(product => {
                html += `
                    <a href="${this.escapeHtml(product.url)}" class="search-product-card">
                        <img src="${this.escapeHtml(product.imagem)}" 
                             alt="${this.escapeHtml(product.nome)}" 
                             class="search-product-image"
                             loading="lazy"
                             onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(product.nome)}&size=300&background=00b96b&color=fff&font-size=0.1'">
                        <div class="search-product-info">
                            <h4 class="search-product-name">${this.escapeHtml(product.nome)}</h4>
                            <p class="search-product-price">${this.escapeHtml(product.currency)} ${this.escapeHtml(product.preco_formatado)}</p>
                            <p class="search-product-company">
                                <i class="fa-solid fa-building"></i> ${this.escapeHtml(product.company_name)}
                            </p>
                        </div>
                    </a>
                `;
            });
            
            html += '</div>';
            
            this.searchResults.innerHTML = html;
            this.searchResults.style.display = 'block';
            
            if (this.searchOverlay) {
                this.searchOverlay.classList.add('active');
            }
        },
        
        displayNoResults(message) {
            if (!this.searchResults) return;
            
            this.searchResults.innerHTML = `
                <div class="search-no-results">
                    <i class="fa-solid fa-search"></i>
                    <h4>Nenhum produto encontrado</h4>
                    <p>${this.escapeHtml(message || 'Tente buscar com outros termos')}</p>
                </div>
            `;
            this.searchResults.style.display = 'block';
            
            if (this.searchOverlay) {
                this.searchOverlay.classList.add('active');
            }
        },
        
        hideResults() {
            if (this.searchResults) {
                this.searchResults.style.display = 'none';
            }
            if (this.searchOverlay) {
                this.searchOverlay.classList.remove('active');
            }
        },
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        showAlert(message) {
            // Usar alert nativo ou implementar modal customizado
            alert(message);
        }
    };
    
    // =================================================================
    // SISTEMA PRINCIPAL
    // =================================================================
    
    const MainSystem = {
        init() {
            this.cacheElements();
            this.bindEvents();
            this.initializeComponents();
        },
        
        cacheElements() {
            this.elements = {
                navBar: document.querySelector('.nav-bar'),
                secHeader: document.getElementById('sec-main-header'),
                categoryGrid: document.getElementById('categoryGrid'),
                scrollLeft: document.getElementById('scrollLeft'),
                scrollRight: document.getElementById('scrollRight'),
                locationDisplay: document.getElementById('locationDisplay'),
                locationTrigger: document.getElementById('locationTrigger'),
                offlineModal: document.getElementById('offlineModal'),
                pageLoader: document.getElementById('pageLoader'),
                backToTop: document.getElementById('backToTop'),
                flashMessage: document.getElementById('flashMessage')
            };
        },
        
        bindEvents() {
            // Page load
            window.addEventListener('load', () => this.hidePageLoader());
            
            // Scroll
            window.addEventListener('scroll', () => this.handleScroll(), { passive: true });
            
            // Online/Offline
            window.addEventListener('online', () => this.checkConnectivity());
            window.addEventListener('offline', () => this.checkConnectivity());
            
            // Resize
            window.addEventListener('resize', () => this.updateCarouselButtons(), { passive: true });
            
            // Back to top
            if (this.elements.backToTop) {
                this.elements.backToTop.addEventListener('click', () => this.scrollToTop());
            }
            
            // Carousel buttons
            if (this.elements.scrollLeft) {
                this.elements.scrollLeft.addEventListener('click', () => this.scrollCarousel(-300));
            }
            if (this.elements.scrollRight) {
                this.elements.scrollRight.addEventListener('click', () => this.scrollCarousel(300));
            }
            
            // Carousel scroll
            if (this.elements.categoryGrid) {
                this.elements.categoryGrid.addEventListener('scroll', () => this.updateCarouselButtons(), { passive: true });
            }
            
            // Location trigger
            if (this.elements.locationTrigger) {
                this.elements.locationTrigger.addEventListener('click', (e) => this.handleLocationClick(e));
            }
        },
        
        initializeComponents() {
            // Auto-fechar flash message
            this.autoCloseFlashMessage();
            
            // Check connectivity
            this.checkConnectivity();
            
            // Update carousel buttons
            this.updateCarouselButtons();
            
            // Lazy load images
            this.initLazyLoad();
        },
        
        hidePageLoader() {
            if (this.elements.pageLoader) {
                this.elements.pageLoader.classList.add('hidden');
                setTimeout(() => {
                    this.elements.pageLoader.style.display = 'none';
                }, 300);
            }
        },
        
        autoCloseFlashMessage() {
            if (this.elements.flashMessage) {
                setTimeout(() => {
                    this.elements.flashMessage.style.opacity = '0';
                    setTimeout(() => {
                        this.elements.flashMessage.remove();
                    }, 300);
                }, 5000);
            }
        },
        
        handleScroll() {
            if (this.ticking) return;
            
            this.ticking = true;
            
            window.requestAnimationFrame(() => {
                const scrollY = window.pageYOffset || document.documentElement.scrollTop;
                
                // Sticky navigation
                this.updateStickyNav(scrollY);
                
                // Back to top button
                this.updateBackToTop(scrollY);
                
                this.ticking = false;
            });
        },
        
        updateStickyNav(scrollY) {
            if (!this.elements.navBar || !this.elements.secHeader) return;
            
            if (scrollY > 50) {
                this.elements.navBar.style.opacity = '0';
                this.elements.navBar.style.pointerEvents = 'none';
                this.elements.secHeader.style.display = 'flex';
                setTimeout(() => {
                    this.elements.secHeader.style.opacity = '1';
                }, 10);
            } else {
                this.elements.navBar.style.opacity = '1';
                this.elements.navBar.style.pointerEvents = 'auto';
                this.elements.secHeader.style.opacity = '0';
                setTimeout(() => {
                    if ((window.pageYOffset || document.documentElement.scrollTop) <= 50) {
                        this.elements.secHeader.style.display = 'none';
                    }
                }, 300);
            }
        },
        
        updateBackToTop(scrollY) {
            if (!this.elements.backToTop) return;
            
            if (scrollY > 300) {
                this.elements.backToTop.classList.add('visible');
            } else {
                this.elements.backToTop.classList.remove('visible');
            }
        },
        
        scrollToTop() {
            window.scrollTo({ 
                top: 0, 
                behavior: 'smooth' 
            });
        },
        
        scrollCarousel(amount) {
            if (!this.elements.categoryGrid) return;
            
            this.elements.categoryGrid.scrollBy({ 
                left: amount, 
                behavior: 'smooth' 
            });
        },
        
        updateCarouselButtons() {
            if (!this.elements.categoryGrid || !this.elements.scrollLeft || !this.elements.scrollRight) return;
            
            const grid = this.elements.categoryGrid;
            
            this.elements.scrollLeft.disabled = grid.scrollLeft <= 0;
            this.elements.scrollRight.disabled = 
                grid.scrollLeft >= (grid.scrollWidth - grid.clientWidth);
        },
        
        checkConnectivity() {
            if (this.elements.offlineModal) {
                this.elements.offlineModal.style.display = navigator.onLine ? 'none' : 'flex';
            }
        },
        
        async handleLocationClick(e) {
            e.preventDefault();
            
            if (!this.elements.locationDisplay) return;
            
            const currentText = this.elements.locationDisplay.textContent.trim();
            
            if (currentText !== 'Selecionar localização') {
                return;
            }
            
            if (!navigator.onLine) {
                this.checkConnectivity();
                return;
            }
            
            alert("Não conseguimos detectar sua região automaticamente.\n\nPara ajudar:\n1. Verifique se o seu GPS está ativo\n2. Desative sua VPN, se estiver usando\n3. Recarregue a página");
            
            await this.updateLocation();
        },
        
        async updateLocation() {
            if (!navigator.onLine) {
                this.checkConnectivity();
                return;
            }
            
            if (this.elements.locationDisplay) {
                this.elements.locationDisplay.textContent = 'Localizando...';
            }
            
            try {
                const response = await fetch('refresh_location.php');
                
                if (!response.ok) {
                    throw new Error('Falha na requisição');
                }
                
                const data = await response.json();
                
                if (this.elements.locationDisplay) {
                    this.elements.locationDisplay.textContent = 
                        (data.country && data.country !== 'Desconhecido' && data.country !== '') 
                        ? data.country 
                        : 'Selecionar localização';
                }
            } catch (error) {
                console.error('Erro ao buscar localização:', error);
                
                if (this.elements.locationDisplay) {
                    this.elements.locationDisplay.textContent = 'Selecionar localização';
                }
            }
        },
        
        initLazyLoad() {
            if (!('IntersectionObserver' in window)) return;
            
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    };
    
    // =================================================================
    // INICIALIZAÇÃO
    // =================================================================
    
    // Inicializar quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            MainSystem.init();
            SearchSystem.init();
        });
    } else {
        // DOM já está pronto
        MainSystem.init();
        SearchSystem.init();
    }
    
    // Expor para uso global se necessário
    window.VSG_Main = MainSystem;
    window.VSG_Search = SearchSystem;
    
})();