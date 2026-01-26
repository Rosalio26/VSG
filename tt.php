<div class="custom-select-wrapper">
    <div class="custom-select">
        <div class="custom-select-trigger">
            <i class="fa-solid fa-list"></i>
            <span>Todas Categorias</span>
        </div>
        <div class="custom-options">
            <div class="custom-option" data-value="">
                <i class="fa-solid fa-list"></i> Todas Categorias
            </div>
            <?php foreach ($all_categories as $cat): ?>
                <div class="custom-option" data-value="<?= $cat['id'] ?>">
                    <i class="fa-solid fa-<?= $cat['icon'] ?>"></i> 
                    <?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                </div>
                <?php if (!empty($cat['subcategories'])): ?>
                    <?php foreach ($cat['subcategories'] as $subcat): ?>
                        <div class="custom-option subcategory" data-value="<?= $subcat['id'] ?>">
                            <i class="fa-solid fa-<?= $subcat['icon'] ?>"></i> 
                            <?= htmlspecialchars($subcat['name']) ?> (<?= $subcat['product_count'] ?>)
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <select name="category" style="display: none;">
        <!-- Select oculto para enviar o formulário -->
    </select>
</div>

<style>
.custom-select-wrapper {
    position: relative;
    width: 100%;
}

.custom-select {
    position: relative;
    cursor: pointer;
}

.custom-select-trigger {
    padding: 12px 15px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.custom-select-trigger i {
    color: #27ae60;
}

.custom-options {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 5px;
    max-height: 300px;
    overflow-y: auto;
    display: none;
    z-index: 1000;
    margin-top: 5px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.custom-select.open .custom-options {
    display: block;
}

.custom-option {
    padding: 10px 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s;
}

.custom-option:hover {
    background: #f0f0f0;
}

.custom-option i {
    color: #27ae60;
    width: 20px;
}

.custom-option.subcategory {
    padding-left: 35px;
    background: #f9f9f9;
}
</style>

<script>
document.querySelector('.custom-select-trigger').addEventListener('click', function() {
    document.querySelector('.custom-select').classList.toggle('open');
});

document.querySelectorAll('.custom-option').forEach(option => {
    option.addEventListener('click', function() {
        const value = this.dataset.value;
        const text = this.textContent;
        
        document.querySelector('.custom-select-trigger span').textContent = text;
        document.querySelector('select[name="category"]').value = value;
        document.querySelector('.custom-select').classList.remove('open');
        
        // Submit do formulário
        this.closest('form').submit();
    });
});

// Fechar ao clicar fora
document.addEventListener('click', function(e) {
    if (!e.target.closest('.custom-select')) {
        document.querySelector('.custom-select').classList.remove('open');
    }
});
</script>