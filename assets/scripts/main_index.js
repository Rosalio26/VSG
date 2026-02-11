/**
 * VSG Marketplace - Main JavaScript OTIMIZADO
 */

(function() {
    'use strict';
    
    // =================================================================
    // CACHE DOM
    // =================================================================
    const DOM = {
        loader: document.getElementById('pageLoader'),
        flash: document.getElementById('flashMessage'),
        searchInput: document.getElementById('searchInput'),
        searchBtn: document.getElementById('searchBtn'),
        clearBtn: document.getElementById('clearSearchBtn'),
        searchResults: document.getElementById('searchResults'),
        categoryGrid: document.getElementById('categoryGrid'),
        scrollLeft: document.getElementById('scrollLeft'),
        scrollRight: document.getElementById('scrollRight'),
        backToTop: document.getElementById('backToTop'),
        navBar: document.querySelector('.nav-bar'),
        secHeader: document.getElementById('sec-main-header')
    };
    
    // =================================================================
    // PAGE LOADER
    // =================================================================
    window.addEventListener('load', () => {
        if (DOM.loader) {
            DOM.loader.classList.add('hidden');
            setTimeout(() => DOM.loader.style.display = 'none', 300);
        }
    });
    
    // =================================================================
    // FLASH MESSAGE
    // =================================================================
    if (DOM.flash) {
        setTimeout(() => {
            DOM.flash.style.opacity = '0';
            setTimeout(() => DOM.flash.remove(), 300);
        }, 5000);
    }
    
    // =================================================================
    // BUSCA SIMPLES
    // =================================================================
    let searchTimeout;
    
    if (DOM.searchInput) {
        DOM.searchInput.addEventListener('input', (e) => {
            const term = e.target.value.trim();
            
            if (DOM.clearBtn) {
                DOM.clearBtn.style.display = term ? 'block' : 'none';
            }
            
            clearTimeout(searchTimeout);
            
            if (term.length >= 2) {
                searchTimeout = setTimeout(() => performSearch(term), 300);
            } else {
                hideResults();
            }
        });
        
        DOM.searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const term = DOM.searchInput.value.trim();
                if (term.length >= 2) {
                    window.location.href = `marketplace.php?search=${encodeURIComponent(term)}`;
                }
            }
        });

        DOM.searchInput.addEventListener('focus', () => {
            const term = DOM.searchInput.value.trim();
            if (term.length >= 2 && DOM.searchResults.innerHTML) {
                DOM.searchResults.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        });
    }
    
    if (DOM.searchBtn) {
        DOM.searchBtn.addEventListener('click', () => {
            const term = DOM.searchInput.value.trim();
            if (term.length >= 2) {
                window.location.href = `marketplace.php?search=${encodeURIComponent(term)}`;
            }
        });
    }
    
    if (DOM.clearBtn) {
        DOM.clearBtn.addEventListener('click', () => {
            DOM.searchInput.value = '';
            DOM.clearBtn.style.display = 'none';
            hideResults();
            DOM.searchInput.focus();
        });
    }
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideResults();
    });

    document.addEventListener('click', (e) => {
        if (DOM.searchResults && 
            !DOM.searchResults.contains(e.target) && 
            !DOM.searchInput.contains(e.target) &&
            e.target !== DOM.clearBtn) {
            hideResults();
        }
    });
    
    async function performSearch(term) {
        if (!DOM.searchResults) return;
        
        DOM.searchResults.innerHTML = '<div class="search-loading"><div class="spinner-small"></div><p>Buscando produtos...</p></div>';
        DOM.searchResults.style.display = 'block';
        
        // Bloquear scroll da página
        document.body.style.overflow = 'hidden';
        
        try {
            const response = await fetch(`pages/app/ajax/ajax_search.php?search=${encodeURIComponent(term)}&limit=8`);
            const data = await response.json();
            
            if (data.success && data.products && data.products.length > 0) {
                displayResults(data, term);
            } else {
                displayNoResults();
            }
        } catch (error) {
            console.error('Erro na busca:', error);
            displayNoResults();
        }
    }
    
    function displayResults(data, searchTerm) {
        if (!DOM.searchResults) return;
        
        let html = `
            <div class="search-results-header">
                <h3>Produtos</h3>
            </div>
            <ul class="search-results-list">
        `;
        
        data.products.forEach((product, index) => {
            const productName = escapeHtml(product.nome);
            const highlightedName = highlightSearchTerm(productName, searchTerm);
            
            html += `
                <li>
                    <a href="${escapeHtml(product.url)}" class="search-result-item" style="animation-delay: ${index * 0.03}s">
                        <img src="${escapeHtml(product.imagem)}" 
                             alt="${productName}" 
                             class="search-result-image"
                             loading="lazy"
                             onerror="this.src='https://ui-avatars.com/api/?name=Produto&size=80&background=00b96b&color=fff'">
                        <div class="search-result-info">
                            <div class="search-result-details">
                                <h4 class="search-result-name">${highlightedName}</h4>
                                <div class="search-result-meta">
                                    <span class="search-result-category">
                                        <i class="fa-solid fa-tag"></i>
                                        ${escapeHtml(product.category_name || 'Produto')}
                                    </span>
                                </div>
                            </div>
                            <div class="search-result-price">
                                <span class="search-result-currency">MT</span>
                                <span>${escapeHtml(product.preco_formatado)}</span>
                            </div>
                        </div>
                    </a>
                </li>
            `;
        });
        
        html += `
            </ul>
            <a href="marketplace.php?search=${encodeURIComponent(DOM.searchInput.value)}" class="search-see-all">
                Ver todos os resultados
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        `;
        
        DOM.searchResults.innerHTML = html;
    }
    
    function displayNoResults() {
        if (!DOM.searchResults) return;
        
        DOM.searchResults.innerHTML = `
            <div class="search-no-results">
                <i class="fa-solid fa-magnifying-glass"></i>
                <h4>Nenhum produto encontrado</h4>
                <p>Tente buscar com outros termos</p>
            </div>
        `;
    }
    
    function hideResults() {
        if (DOM.searchResults) DOM.searchResults.style.display = 'none';
        
        // Desbloquear scroll da página
        document.body.style.overflow = '';
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function highlightSearchTerm(text, term) {
        if (!term) return text;
        const regex = new RegExp(`(${escapeRegex(term)})`, 'gi');
        return text.replace(regex, '<span class="search-highlight">$1</span>');
    }

    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // =================================================================
    // CAROUSEL
    // =================================================================
    if (DOM.scrollLeft && DOM.categoryGrid) {
        DOM.scrollLeft.addEventListener('click', () => {
            DOM.categoryGrid.scrollBy({ left: -300, behavior: 'smooth' });
        });
    }
    
    if (DOM.scrollRight && DOM.categoryGrid) {
        DOM.scrollRight.addEventListener('click', () => {
            DOM.categoryGrid.scrollBy({ left: 300, behavior: 'smooth' });
        });
    }
    
    if (DOM.categoryGrid) {
        DOM.categoryGrid.addEventListener('scroll', updateCarouselButtons);
        updateCarouselButtons();
    }
    
    function updateCarouselButtons() {
        if (!DOM.categoryGrid || !DOM.scrollLeft || !DOM.scrollRight) return;
        
        DOM.scrollLeft.disabled = DOM.categoryGrid.scrollLeft <= 0;
        DOM.scrollRight.disabled = 
            DOM.categoryGrid.scrollLeft >= (DOM.categoryGrid.scrollWidth - DOM.categoryGrid.clientWidth - 10);
    }
    
    // =================================================================
    // SCROLL
    // =================================================================
    let ticking = false;
    
    window.addEventListener('scroll', () => {
        if (ticking) return;
        
        ticking = true;
        requestAnimationFrame(() => {
            const scrollY = window.pageYOffset;
            
            // Sticky nav
            if (DOM.navBar && DOM.secHeader) {
                if (scrollY > 50) {
                    DOM.navBar.style.opacity = '0';
                    DOM.navBar.style.pointerEvents = 'none';
                    DOM.secHeader.style.display = 'flex';
                    setTimeout(() => DOM.secHeader.style.opacity = '1', 10);
                } else {
                    DOM.navBar.style.opacity = '1';
                    DOM.navBar.style.pointerEvents = 'auto';
                    DOM.secHeader.style.opacity = '0';
                    setTimeout(() => {
                        if (window.pageYOffset <= 50) {
                            DOM.secHeader.style.display = 'none';
                        }
                    }, 300);
                }
            }
            
            // Back to top
            if (DOM.backToTop) {
                DOM.backToTop.style.display = scrollY > 300 ? 'block' : 'none';
            }
            
            ticking = false;
        });
    }, { passive: true });
    
    if (DOM.backToTop) {
        DOM.backToTop.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    
    // =================================================================
    // RESIZE
    // =================================================================
    window.addEventListener('resize', () => {
        updateCarouselButtons();
    }, { passive: true });
    
    console.log('✅ VSG Marketplace carregado');
    
})();