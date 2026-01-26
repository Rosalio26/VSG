// Add to cart functionality
function addToCart(event, productId) {
    event.stopPropagation();
    const btn = event.currentTarget;
    const originalHTML = btn.innerHTML;
    
    fetch('ajax/add-to-cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId, quantity: 1 })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Adicionado!';
            btn.style.background = 'var(--secondary-green)';
            
            const cartBadge = document.querySelector('.cart-badge');
            if (cartBadge) {
                cartBadge.textContent = data.cart_count;
            } else if (data.cart_count > 0) {
                const cartAction = document.querySelector('.header-action .fa-cart-shopping').parentElement;
                const badge = document.createElement('span');
                badge.className = 'cart-badge';
                badge.textContent = data.cart_count;
                cartAction.appendChild(badge);
            }
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.style.background = 'var(--primary-green)';
            }, 2000);
        } else {
            alert(data.message || 'Erro ao adicionar ao carrinho');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Por favor, faça login para adicionar produtos ao carrinho.');
    });
}

// Favorite toggle
// Favorite toggle - ATUALIZADO
function toggleFavorite(event, productId) {
    event.stopPropagation();
    const btn = event.currentTarget;
    const icon = btn.querySelector('i');
    
    fetch('pages/person/ajax/toggle-favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.favorited) {
                icon.classList.remove('fa-regular');
                icon.classList.add('fa-solid');
                btn.classList.add('favorited');
            } else {
                icon.classList.remove('fa-solid');
                icon.classList.add('fa-regular');
                btn.classList.remove('favorited');
            }
            
            // Mostrar toast de sucesso
            showToast(data.message, 'success');
        } else {
            if (data.message.includes('login')) {
                if (confirm('Você precisa fazer login. Deseja ir para a página de login?')) {
                    window.location.href = 'registration/login/login.php';
                }
            } else {
                showToast(data.message, 'error');
            }
        }
    })
    .catch(() => {
        if (confirm('Você precisa fazer login. Deseja ir para a página de login?')) {
            window.location.href = 'registration/login/login.php';
        }
    });
}

// Toast notification simples
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 9999;
        animation: slideIn 0.3s ease-out;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Adicionar animações CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Quick view
function quickView(event, productId) {
    event.stopPropagation();
    window.open('product.php?id=' + productId, '_blank');
}

// Update sort
function updateSort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// Price filter functions
function clearPriceFilter() {
    document.querySelector('input[name="min_price"]').value = '';
    document.querySelector('input[name="max_price"]').value = '';
    document.querySelector('.price-slider').value = '10000';
    document.getElementById('maxPriceDisplay').textContent = '10,000';
}

function updateMaxPrice(value) {
    document.querySelector('input[name="max_price"]').value = value;
    document.getElementById('maxPriceDisplay').textContent = parseInt(value).toLocaleString();
}

// Category filter functions
function clearCategoryFilters() {
    document.querySelectorAll('input[name="categories[]"]').forEach(cb => cb.checked = false);
    document.getElementById('filterForm').submit();
}

function removeCategoryFilter(catId) {
    const checkbox = document.querySelector(`input[name="categories[]"][value="${catId}"]`);
    if (checkbox) {
        checkbox.checked = false;
        document.getElementById('filterForm').submit();
    }
}

function toggleSubcategories(catId) {
    const subcatDiv = document.getElementById('subcat-' + catId);
    if (subcatDiv) {
        subcatDiv.style.display = subcatDiv.style.display === 'none' ? 'block' : 'none';
    }
}

// Eco badges functions
function clearEcoBadges() {
    document.querySelectorAll('input[name="eco_badges[]"]').forEach(cb => cb.checked = false);
    document.querySelectorAll('.eco-badge-filter').forEach(div => div.classList.remove('active'));
    document.getElementById('filterForm').submit();
}

function toggleEcoBadge(badgeKey) {
    const checkbox = document.getElementById('eco' + badgeKey);
    checkbox.checked = !checkbox.checked;
    event.currentTarget.classList.toggle('active');
}

function removeEcoBadge(badge) {
    const checkbox = document.querySelector(`input[name="eco_badges[]"][value="${badge}"]`);
    if (checkbox) {
        checkbox.checked = false;
        document.getElementById('filterForm').submit();
    }
}

// Rating functions
function clearRating() {
    document.querySelectorAll('input[name="min_rating"]').forEach(rb => rb.checked = false);
}

// Search filter
function removeSearchFilter() {
    const url = new URL(window.location.href);
    url.searchParams.delete('q');
    window.location.href = url.toString();
}

// View toggle
const viewBtns = document.querySelectorAll('.view-btn');
viewBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        viewBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    });
});
    
$(document).ready(function() {
    // Inicializar Select2 - SELECT 200px / DROPDOWN 400px
    $('#searchCategorySelect').select2({
        templateResult: formatCategoryOption,
        templateSelection: formatCategorySelection,
        minimumResultsForSearch: 5,
        width: '200px', // SELECT de 200px
        dropdownCssClass: 'select2-dropdown-400', // Classe customizada para dropdown
        placeholder: 'Todas Categorias',
        language: {
            noResults: function() {
                return "Nenhuma categoria encontrada";
            },
            searching: function() {
                return "Buscando...";
            }
        }
    });

    // Formatar opções no dropdown (com ícone)
    function formatCategoryOption(option) {
        if (!option.id) {
            return option.text;
        }

        var icon = $(option.element).data('icon');
        var text = option.text;
        var isSubcategory = text.trim().startsWith('↳');

        if (!icon) {
            return $('<span style="font-size: 14px;">' + text + '</span>');
        }

        var $option = $(
            '<span style="display: flex; align-items: center; gap: 10px;">' +
                '<i class="fa-solid fa-' + icon + '" style="font-size: 16px;"></i> ' +
                '<span style="font-size: 14px;">' + text + '</span>' +
            '</span>'
        );

        if (isSubcategory) {
            $option.addClass('subcategory');
        }

        return $option;
    }

    // Formatar seleção (o que aparece no select quando fechado)
    function formatCategorySelection(option) {
        if (!option.id) {
            return $('<span style="font-size: 14px;">' + option.text + '</span>');
        }

        var icon = $(option.element).data('icon');
        var text = option.text.replace('↳', '').trim();

        if (!icon) {
            return $('<span style="font-size: 14px;">' + text + '</span>');
        }

        return $(
            '<span style="display: flex; align-items: center; gap: 10px;">' +
                '<i class="fa-solid fa-' + icon + '" style="font-size: 16px; color: var(--primary-green);"></i> ' +
                '<span style="font-size: 14px; font-weight: 500;">' + text + '</span>' +
            '</span>'
        );
    }

    // Submit do formulário ao mudar categoria
    $('#searchCategorySelect').on('change', function() {
        $(this).closest('form').submit();
    });
});