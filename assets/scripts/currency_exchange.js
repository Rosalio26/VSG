/**
 * VSG Marketplace - Currency Exchange JavaScript (Versão 3.1)
 * Sistema automático de conversão com APIs em tempo real
 * Correção: Proteção contra valores nulos e erros de Syntax no JSON
 */

(function() {
    'use strict';
    
    // =================================================================
    // CONVERSOR DINÂMICO DE PREÇOS
    // =================================================================
    
    class DynamicPriceConverter {
        constructor() {
            this.baseCurrency = 'MZN';
            this.currentCurrency = this.getCurrentCurrency();
            this.exchangeRates = {};
            this.isLoading = false;
            this.retryCount = 0;
            this.maxRetries = 3;
            
            this.init();
        }
        
        async init() {
            // Buscar taxas de câmbio
            await this.fetchExchangeRates();
            
            // Atualizar preços iniciais
            this.updateAllPrices();
            
            // Observar mudanças de moeda
            window.addEventListener('currency-changed', () => {
                this.currentCurrency = this.getCurrentCurrency();
                this.updateAllPrices();
            });
            
            // Atualizar taxas periodicamente (a cada 30 minutos)
            setInterval(() => {
                this.fetchExchangeRates(true);
            }, 30 * 60 * 1000);
        }
        
        getCurrentCurrency() {
            try {
                // 1. Tentar localStorage
                const stored = localStorage.getItem('vsg_preferred_currency');
                if (stored && typeof stored === 'string') return stored.toUpperCase();
                
                // 2. Tentar cookie
                const cookie = document.cookie.match(/vsg_currency=([^;]+)/);
                if (cookie && cookie[1]) return cookie[1].toUpperCase();
                
                // 3. Detectar do país (se disponível na página)
                const countryEl = document.getElementById('locationDisplay');
                if (countryEl) {
                    const country = countryEl.textContent.trim().toUpperCase();
                    const currencyMap = {
                        'PT': 'EUR', 'BR': 'BRL', 'US': 'USD', 'GB': 'GBP',
                        'ES': 'EUR', 'FR': 'EUR', 'DE': 'EUR', 'IT': 'EUR',
                        'CA': 'CAD', 'AU': 'AUD', 'MX': 'MXN', 'AR': 'ARS',
                        'AO': 'AOA', 'ZA': 'ZAR', 'MZ': 'MZN'
                    };
                    
                    if (currencyMap[country]) {
                        return currencyMap[country];
                    }
                }
            } catch (e) {
                console.error('[VSG Currency] Erro ao detectar moeda:', e);
            }
            
            // 4. Default de segurança absoluta
            return this.baseCurrency || 'MZN';
        }
        
        async fetchExchangeRates(silent = false) {
            if (this.isLoading) return;
            
            this.isLoading = true;
            
            if (!silent) {
                this.showLoadingIndicator();
            }
            
            try {
                // Usamos um timestamp para evitar cache do navegador que possa conter erros
                const response = await fetch(`/api/get_exchange_rates.php?t=${Date.now()}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                const text = await response.text();
                
                // Tenta parsear o JSON, mas captura erro se o PHP retornar HTML/Erro
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('[VSG Currency] Resposta do servidor não é um JSON válido. Verifique a API.');
                    throw new Error('Invalid JSON response');
                }
                
                if (data.success && data.rates) {
                    this.exchangeRates = data.rates;
                    this.retryCount = 0;
                    
                    // Salvar no localStorage como backup
                    localStorage.setItem('vsg_exchange_rates', JSON.stringify({
                        rates: data.rates,
                        timestamp: Date.now()
                    }));
                    
                    if (!silent) {
                        this.updateAllPrices();
                    }
                } else {
                    throw new Error(data.error || 'Formato de resposta inválido');
                }
            } catch (error) {
                console.error('Erro ao buscar taxas de câmbio:', error);
                
                // Tentar carregar do localStorage
                this.loadFromCache();
                
                // Retry com backoff
                if (this.retryCount < this.maxRetries) {
                    this.retryCount++;
                    const delay = Math.min(1000 * Math.pow(2, this.retryCount), 10000);
                    setTimeout(() => {
                        this.isLoading = false;
                        this.fetchExchangeRates(silent);
                    }, delay);
                }
            } finally {
                this.isLoading = false;
                if (!silent) {
                    this.hideLoadingIndicator();
                }
            }
        }
        
        loadFromCache() {
            try {
                const cached = localStorage.getItem('vsg_exchange_rates');
                if (cached) {
                    const data = JSON.parse(cached);
                    const age = Date.now() - data.timestamp;
                    
                    // Usar cache se tiver menos de 24 horas
                    if (age < 24 * 60 * 60 * 1000) {
                        this.exchangeRates = data.rates;
                        if (this.debugMode()) console.log('[VSG Currency] Usando taxas do cache');
                    }
                }
            } catch (e) {
                console.error('Erro ao carregar cache:', e);
            }
        }
        
        convert(amount, from, to) {
            // SEGURANÇA: Se faltar parâmetros ou taxas, retorna o valor original sem erro
            if (amount === undefined || amount === null) return 0;
            if (!from || !to) return amount;
            if (from === to) return amount;
            
            // Proteção contra toUpperCase em undefined
            from = String(from).toUpperCase();
            to = String(to).toUpperCase();
            
            if (!this.exchangeRates || Object.keys(this.exchangeRates).length === 0) {
                return amount;
            }
            
            // Conversão direta
            const rateKey = `${from}_${to}`;
            if (this.exchangeRates[rateKey]) {
                return amount * this.exchangeRates[rateKey];
            }
            
            // Conversão via base currency (MZN)
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
            
            return amount; // Fallback
        }
        
        updateAllPrices() {
            const elements = document.querySelectorAll('[data-price-mzn]');
            const targetCurrency = this.currentCurrency || this.baseCurrency;
            
            elements.forEach(element => {
                const priceMZN = parseFloat(element.dataset.priceMzn);
                if (isNaN(priceMZN)) return;
                
                const converted = this.convert(priceMZN, 'MZN', targetCurrency);
                const formatted = this.formatPrice(converted, targetCurrency);
                
                element.textContent = formatted;
                element.setAttribute('data-converted-amount', converted.toFixed(2));
            });
        }
        
        formatPrice(amount, currency) {
            const symbols = {
                'MZN': 'MT',   'EUR': '€',    'BRL': 'R$',   'USD': '$',
                'GBP': '£',    'CAD': 'CA$',  'AUD': 'A$',   'JPY': '¥',
                'CNY': '¥',    'CHF': 'Fr',   'MXN': 'MX$',  'ARS': 'AR$'
            };
            
            const symbol = symbols[currency] || currency;
            const decimals = ['JPY', 'KRW'].includes(currency) ? 0 : 2;
            
            try {
                // Tenta usar formatador nativo para melhor UX local
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
        
        debugMode() {
            return localStorage.getItem('vsg_debug_currency') === 'true';
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
                if (!this.element.contains(e.target)) this.closeMenu();
            });
        }
        
        toggleMenu() { this.element.classList.toggle('active'); }
        closeMenu() { this.element.classList.remove('active'); }
        
        selectCurrency(option) {
            const currency = option.dataset.currency;
            if (!currency) return;
            
            localStorage.setItem('vsg_preferred_currency', currency.toUpperCase());
            const expires = new Date();
            expires.setDate(expires.getDate() + 30);
            document.cookie = `vsg_currency=${currency.toUpperCase()}; expires=${expires.toUTCString()}; path=/`;
            
            window.dispatchEvent(new CustomEvent('currency-changed', { detail: { currency } }));
            this.closeMenu();
            this.updateDisplay(currency.toUpperCase());
        }
        
        updateDisplay(currency) {
            const display = this.element.querySelector('.current-currency-code');
            if (display) display.textContent = currency;
        }
    }
    
    // =================================================================
    // INICIALIZAÇÃO
    // =================================================================
    
    const priceConverter = new DynamicPriceConverter();
    window.VSG_PriceConverter = priceConverter;
    
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.currency-selector').forEach(element => {
            new CurrencySelector(element);
        });
    });
    
    window.VSG_Currency = {
        convert: (amount, from, to) => priceConverter.convert(amount, from, to),
        formatPrice: (amount, currency) => priceConverter.formatPrice(amount, currency),
        getCurrentCurrency: () => priceConverter.getCurrentCurrency(),
        refreshRates: () => priceConverter.fetchExchangeRates()
    };
    
})();