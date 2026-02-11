/**
 * VSG Marketplace - Currency Exchange JavaScript (Versão 4.0 - OTIMIZADA)
 * Sistema automático de conversão com APIs em tempo real
 * OTIMIZAÇÕES:
 * - Cache agressivo de taxas (reduz chamadas à API)
 * - Conversão lazy/on-demand (só converte quando necessário)
 * - Debouncing de updates
 * - RequestAnimationFrame para updates visuais
 * - Intersection Observer para conversão de elementos visíveis apenas
 */

(function() {
    'use strict';
    
    // =================================================================
    // CONFIGURAÇÕES
    // =================================================================
    const CONFIG = {
        BASE_CURRENCY: 'MZN',
        CACHE_DURATION: 6 * 60 * 60 * 1000, // 6 horas (antes era 30 min)
        UPDATE_DEBOUNCE: 150, // ms
        MAX_RETRIES: 2, // reduzido de 3
        RETRY_DELAY: 2000, // ms
        ENABLE_INTERSECTION_OBSERVER: true // Só converte o que está visível
    };
    
    // =================================================================
    // CONVERSOR DINÂMICO DE PREÇOS (OTIMIZADO)
    // =================================================================
    
    class DynamicPriceConverter {
        constructor() {
            this.baseCurrency = CONFIG.BASE_CURRENCY;
            this.currentCurrency = null;
            this.exchangeRates = {};
            this.isLoading = false;
            this.retryCount = 0;
            this.updateTimeout = null;
            this.observer = null;
            this.elementsToConvert = new Set();
            
            this.init();
        }
        
        async init() {
            // Detectar moeda ANTES de buscar taxas
            this.currentCurrency = this.getCurrentCurrency();
            
            // Se a moeda já é a base, não precisa converter
            if (this.currentCurrency === this.baseCurrency) {
                console.log('[VSG Currency] Moeda base detectada, conversão desabilitada');
                return;
            }
            
            // Carregar taxas do cache primeiro (instantâneo)
            const cacheLoaded = this.loadFromCache();
            
            if (cacheLoaded) {
                // Se tem cache válido, usar ele e atualizar preços imediatamente
                this.scheduleUpdate();
                
                // Buscar novas taxas em background (não bloqueia)
                this.fetchExchangeRates(true);
            } else {
                // Sem cache, buscar taxas (com loading)
                await this.fetchExchangeRates(false);
                this.scheduleUpdate();
            }
            
            // Setup Intersection Observer (só converte elementos visíveis)
            if (CONFIG.ENABLE_INTERSECTION_OBSERVER) {
                this.setupIntersectionObserver();
            }
            
            // Observar mudanças de moeda
            window.addEventListener('currency-changed', () => {
                this.currentCurrency = this.getCurrentCurrency();
                this.scheduleUpdate();
            });
            
            // Atualizar taxas periodicamente (em background)
            setInterval(() => {
                this.fetchExchangeRates(true);
            }, CONFIG.CACHE_DURATION);
        }
        
        getCurrentCurrency() {
            try {
                // 1. localStorage (mais rápido)
                const stored = localStorage.getItem('vsg_preferred_currency');
                if (stored && typeof stored === 'string') return stored.toUpperCase();
                
                // 2. Cookie
                const cookie = document.cookie.match(/vsg_currency=([^;]+)/);
                if (cookie && cookie[1]) return cookie[1].toUpperCase();
                
                // 3. Detectar do elemento na página (se disponível)
                const currencyDisplay = document.getElementById('currentCurrencyDisplay');
                if (currencyDisplay && currencyDisplay.textContent) {
                    return currencyDisplay.textContent.trim().toUpperCase();
                }
            } catch (e) {
                console.error('[VSG Currency] Erro ao detectar moeda:', e);
            }
            
            // Fallback para moeda base
            return this.baseCurrency;
        }
        
        async fetchExchangeRates(silent = false) {
            // Evitar múltiplas requisições simultâneas
            if (this.isLoading) return;
            
            this.isLoading = true;
            
            if (!silent) {
                this.showLoadingIndicator();
            }
            
            try {
                const response = await fetch(`/api/get_exchange_rates.php?t=${Date.now()}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    signal: AbortSignal.timeout(5000) // Timeout de 5s
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                
                const text = await response.text();
                let data;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('[VSG Currency] Resposta inválida da API');
                    throw new Error('Invalid JSON response');
                }
                
                if (data.success && data.rates) {
                    this.exchangeRates = data.rates;
                    this.retryCount = 0;
                    
                    // Salvar no cache
                    this.saveToCache(data.rates);
                    
                    if (!silent) {
                        this.scheduleUpdate();
                    }
                } else {
                    throw new Error(data.error || 'Formato inválido');
                }
            } catch (error) {
                console.error('[VSG Currency] Erro ao buscar taxas:', error.message);
                
                // Carregar cache como fallback
                if (!this.loadFromCache()) {
                    // Retry com backoff exponencial
                    if (this.retryCount < CONFIG.MAX_RETRIES) {
                        this.retryCount++;
                        const delay = CONFIG.RETRY_DELAY * this.retryCount;
                        setTimeout(() => {
                            this.isLoading = false;
                            this.fetchExchangeRates(silent);
                        }, delay);
                    }
                }
            } finally {
                this.isLoading = false;
                if (!silent) {
                    this.hideLoadingIndicator();
                }
            }
        }
        
        saveToCache(rates) {
            try {
                localStorage.setItem('vsg_exchange_rates', JSON.stringify({
                    rates: rates,
                    timestamp: Date.now()
                }));
            } catch (e) {
                console.warn('[VSG Currency] Erro ao salvar cache:', e);
            }
        }
        
        loadFromCache() {
            try {
                const cached = localStorage.getItem('vsg_exchange_rates');
                if (!cached) return false;
                
                const data = JSON.parse(cached);
                const age = Date.now() - data.timestamp;
                
                // Cache válido por CONFIG.CACHE_DURATION
                if (age < CONFIG.CACHE_DURATION) {
                    this.exchangeRates = data.rates;
                    console.log('[VSG Currency] Cache carregado (idade: ' + Math.round(age / 60000) + ' min)');
                    return true;
                }
            } catch (e) {
                console.error('[VSG Currency] Erro ao carregar cache:', e);
            }
            
            return false;
        }
        
        convert(amount, from, to) {
            if (amount === undefined || amount === null || isNaN(amount)) return 0;
            if (!from || !to) return amount;
            
            from = String(from).toUpperCase();
            to = String(to).toUpperCase();
            
            if (from === to) return amount;
            
            // Se não tem taxas, retorna original
            if (!this.exchangeRates || Object.keys(this.exchangeRates).length === 0) {
                return amount;
            }
            
            // Conversão direta
            const rateKey = `${from}_${to}`;
            if (this.exchangeRates[rateKey]) {
                return amount * this.exchangeRates[rateKey];
            }
            
            // Conversão via moeda base
            const fromToBase = this.exchangeRates[`${from}_${this.baseCurrency}`];
            const baseToTarget = this.exchangeRates[`${this.baseCurrency}_${to}`];
            
            if (fromToBase && baseToTarget) {
                return amount * fromToBase * baseToTarget;
            }
            
            // Conversão inversa
            const inverseKey = `${to}_${from}`;
            if (this.exchangeRates[inverseKey]) {
                return amount / this.exchangeRates[inverseKey];
            }
            
            return amount;
        }
        
        // =================================================================
        // OTIMIZAÇÃO: Intersection Observer
        // Só converte elementos que estão visíveis na tela
        // =================================================================
        setupIntersectionObserver() {
            if (!('IntersectionObserver' in window)) {
                // Fallback: converter tudo
                this.updateAllPrices();
                return;
            }
            
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.convertElement(entry.target);
                        this.observer.unobserve(entry.target); // Só converte uma vez
                    }
                });
            }, {
                rootMargin: '50px' // Começar a converter 50px antes de ficar visível
            });
            
            // Observar todos os elementos com data-price-mzn
            document.querySelectorAll('[data-price-mzn]').forEach(el => {
                this.observer.observe(el);
            });
        }
        
        convertElement(element) {
            const priceMZN = parseFloat(element.dataset.priceMzn);
            if (isNaN(priceMZN)) return;
            
            const converted = this.convert(priceMZN, 'MZN', this.currentCurrency);
            const formatted = this.formatPrice(converted, this.currentCurrency);
            
            element.textContent = formatted;
            element.setAttribute('data-converted', 'true');
        }
        
        // =================================================================
        // OTIMIZAÇÃO: Debounced update
        // Agrupa múltiplas chamadas de update em uma só
        // =================================================================
        scheduleUpdate() {
            if (this.updateTimeout) {
                clearTimeout(this.updateTimeout);
            }
            
            this.updateTimeout = setTimeout(() => {
                requestAnimationFrame(() => {
                    this.updateAllPrices();
                });
            }, CONFIG.UPDATE_DEBOUNCE);
        }
        
        updateAllPrices() {
            // Se a moeda atual é a base, não precisa fazer nada
            if (this.currentCurrency === this.baseCurrency) {
                return;
            }
            
            // Se está usando Intersection Observer, ele já cuidou de tudo
            if (this.observer) {
                return;
            }
            
            // Fallback: converter todos de uma vez (em lote)
            const elements = document.querySelectorAll('[data-price-mzn]:not([data-converted])');
            
            // Usar requestAnimationFrame para não bloquear a UI
            const batchSize = 50;
            let index = 0;
            
            const processBatch = () => {
                const end = Math.min(index + batchSize, elements.length);
                
                for (let i = index; i < end; i++) {
                    this.convertElement(elements[i]);
                }
                
                index = end;
                
                if (index < elements.length) {
                    requestAnimationFrame(processBatch);
                }
            };
            
            processBatch();
        }
        
        formatPrice(amount, currency) {
            const symbols = {
                'MZN': 'MT',   'EUR': '€',    'BRL': 'R$',   'USD': '$',
                'GBP': '£',    'CAD': 'CA$',  'AUD': 'A$',   'JPY': '¥',
                'CNY': '¥',    'CHF': 'Fr',   'MXN': 'MX$',  'ARS': 'AR$',
                'AOA': 'Kz',   'ZAR': 'R'
            };
            
            const symbol = symbols[currency] || currency;
            const decimals = ['JPY', 'KRW'].includes(currency) ? 0 : 2;
            
            try {
                const formatted = new Intl.NumberFormat('pt-MZ', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                }).format(amount);
                
                const prefixCurrencies = ['USD', 'GBP', 'EUR', 'CAD', 'AUD', 'CHF'];
                return prefixCurrencies.includes(currency) 
                    ? `${symbol} ${formatted}`
                    : `${formatted} ${symbol}`;
            } catch (e) {
                return `${amount.toFixed(decimals)} ${symbol}`;
            }
        }
        
        showLoadingIndicator() {
            const indicator = document.getElementById('currencyLoadingIndicator');
            if (indicator) indicator.style.display = 'block';
        }
        
        hideLoadingIndicator() {
            const indicator = document.getElementById('currencyLoadingIndicator');
            if (indicator) indicator.style.display = 'none';
        }
    }
    
    // =================================================================
    // SELETOR DE MOEDA
    // =================================================================
    
    class CurrencySelector {
        constructor(element) {
            this.element = element;
            this.toggle = element.querySelector('.currency-dropdown-toggle');
            this.menu = element.querySelector('.currency-dropdown-menu');
            this.options = element.querySelectorAll('.currency-option');
            this.init();
        }
        
        init() {
            if (this.toggle) {
                this.toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleMenu();
                });
            }
            
            this.options.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.selectCurrency(option);
                });
            });
            
            document.addEventListener('click', (e) => {
                if (!this.element.contains(e.target)) {
                    this.closeMenu();
                }
            });
        }
        
        toggleMenu() { 
            this.element.classList.toggle('active'); 
        }
        
        closeMenu() { 
            this.element.classList.remove('active'); 
        }
        
        selectCurrency(option) {
            const currency = option.dataset.currency;
            if (!currency) return;
            
            const currencyUpper = currency.toUpperCase();
            
            // Salvar preferência
            localStorage.setItem('vsg_preferred_currency', currencyUpper);
            
            const expires = new Date();
            expires.setDate(expires.getDate() + 30);
            document.cookie = `vsg_currency=${currencyUpper}; expires=${expires.toUTCString()}; path=/`;
            
            // Disparar evento
            window.dispatchEvent(new CustomEvent('currency-changed', { 
                detail: { currency: currencyUpper } 
            }));
            
            this.closeMenu();
            this.updateDisplay(currencyUpper);
        }
        
        updateDisplay(currency) {
            const display = this.element.querySelector('.current-currency-code');
            if (display) display.textContent = currency;
            
            const displayHeader = document.getElementById('currentCurrencyDisplay');
            if (displayHeader) displayHeader.textContent = currency;
        }
    }
    
    // =================================================================
    // INICIALIZAÇÃO
    // =================================================================
    
    let priceConverter = null;
    
    // Iniciar assim que o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCurrency);
    } else {
        initCurrency();
    }
    
    function initCurrency() {
        priceConverter = new DynamicPriceConverter();
        window.VSG_PriceConverter = priceConverter;
        
        // Inicializar seletores de moeda
        document.querySelectorAll('.currency-selector').forEach(element => {
            new CurrencySelector(element);
        });
        
        // API pública
        window.VSG_Currency = {
            convert: (amount, from, to) => priceConverter.convert(amount, from, to),
            formatPrice: (amount, currency) => priceConverter.formatPrice(amount, currency),
            getCurrentCurrency: () => priceConverter.getCurrentCurrency(),
            refreshRates: () => priceConverter.fetchExchangeRates(false)
        };
    }
    
})();