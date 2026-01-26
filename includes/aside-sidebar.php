<!-- Filters Sidebar -->
<aside class="filters-sidebar">
    <form id="filterForm" method="GET" action="marketplace.php">
        <!-- Categorias Principais -->
        <div class="filter-section">
            <div class="filter-title">
                <span><i class="fa-solid fa-layer-group"></i> Categorias</span>
                <?php if (!empty($selected_categories)): ?>
                    <span class="clear-filter" onclick="clearCategoryFilters()">Limpar</span>
                <?php endif; ?>
            </div>
            <?php foreach (array_slice($all_categories, 0, 8) as $category): ?>
                <div class="filter-option">
                    <input type="checkbox" 
                            name="categories[]" 
                            id="cat<?= $category['id'] ?>"
                            value="<?= $category['id'] ?>"
                            <?= in_array($category['id'], $selected_categories) ? 'checked' : '' ?>
                            onchange="toggleSubcategories(<?= $category['id'] ?>)">
                    <label for="cat<?= $category['id'] ?>">
                        <i class="fa-solid fa-<?= htmlspecialchars($category['icon']) ?> category-icon"></i>
                        <?= htmlspecialchars($category['name']) ?>
                    </label>
                    <span class="filter-count"><?= number_format($category['product_count']) ?></span>
                </div>
                
                <?php if (!empty($category['subcategories']) && in_array($category['id'], $selected_categories)): ?>
                    <div class="subcategory-list" id="subcat-<?= $category['id'] ?>">
                        <?php foreach ($category['subcategories'] as $subcat): ?>
                            <div class="filter-option subcategory-option">
                                <input type="checkbox" 
                                        name="categories[]" 
                                        id="cat<?= $subcat['id'] ?>"
                                        value="<?= $subcat['id'] ?>"
                                        <?= in_array($subcat['id'], $selected_categories) ? 'checked' : '' ?>>
                                <label for="cat<?= $subcat['id'] ?>">
                                    <?= htmlspecialchars($subcat['name']) ?>
                                </label>
                                <span class="filter-count"><?= number_format($subcat['product_count']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Faixa de Preço -->
        <div class="filter-section">
            <div class="filter-title">
                <span><i class="fa-solid fa-money-bill-wave"></i> Faixa de Preço</span>
                <span class="clear-filter" onclick="clearPriceFilter()">Limpar</span>
            </div>
            <div class="price-inputs">
                <input type="number" 
                        class="price-input" 
                        name="min_price" 
                        placeholder="Mín" 
                        value="<?= $min_price > 0 ? $min_price : '' ?>">
                <input type="number" 
                        class="price-input" 
                        name="max_price" 
                        placeholder="Máx" 
                        value="<?= $max_price < 100000 ? $max_price : '' ?>">
            </div>
            <input type="range" 
                    class="price-slider" 
                    min="0" 
                    max="10000" 
                    step="100"
                    value="<?= $max_price < 100000 ? $max_price : 10000 ?>"
                    oninput="updateMaxPrice(this.value)">
            <div style="font-size: 12px; color: var(--gray-600); margin-top: 8px; text-align: center;">
                até MZN <span id="maxPriceDisplay"><?= number_format($max_price < 100000 ? $max_price : 10000) ?></span>
            </div>
        </div>

        <!-- Eco Badges -->
        <div class="filter-section">
            <div class="filter-title">
                <span><i class="fa-solid fa-leaf"></i> Atributos Ecológicos</span>
                <?php if (!empty($eco_badges)): ?>
                    <span class="clear-filter" onclick="clearEcoBadges()">Limpar</span>
                <?php endif; ?>
            </div>
            <?php foreach (array_slice($eco_badges_available, 0, 6, true) as $badge_key => $badge_info): ?>
                <div class="eco-badge-filter <?= in_array($badge_key, $eco_badges) ? 'active' : '' ?>" 
                        onclick="toggleEcoBadge('<?= $badge_key ?>')">
                    <input type="checkbox" 
                            name="eco_badges[]" 
                            id="eco<?= $badge_key ?>"
                            value="<?= $badge_key ?>"
                            <?= in_array($badge_key, $eco_badges) ? 'checked' : '' ?>>
                    <label for="eco<?= $badge_key ?>" style="display: contents;">
                        <i class="fa-solid fa-<?= $badge_info['icon'] ?> eco-badge-icon" 
                            style="color: <?= $badge_info['color'] ?>;"></i>
                        <span class="eco-badge-label"><?= $badge_info['label'] ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Avaliação -->
        <div class="filter-section">
            <div class="filter-title">
                <span><i class="fa-solid fa-star"></i> Avaliação</span>
                <?php if ($min_rating > 0): ?>
                    <span class="clear-filter" onclick="clearRating()">Limpar</span>
                <?php endif; ?>
            </div>
            <div class="rating-option">
                <input type="radio" name="min_rating" id="rating5" value="5" 
                        <?= $min_rating == 5 ? 'checked' : '' ?>>
                <label for="rating5" style="display: contents;">
                    <div class="stars">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                    </div>
                    <span style="font-size: 13px; color: var(--gray-600);">e acima</span>
                </label>
            </div>
            <div class="rating-option">
                <input type="radio" name="min_rating" id="rating4" value="4" 
                        <?= $min_rating == 4 ? 'checked' : '' ?>>
                <label for="rating4" style="display: contents;">
                    <div class="stars">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                    </div>
                    <span style="font-size: 13px; color: var(--gray-600);">e acima</span>
                </label>
            </div>
            <div class="rating-option">
                <input type="radio" name="min_rating" id="rating3" value="3" 
                        <?= $min_rating == 3 ? 'checked' : '' ?>>
                <label for="rating3" style="display: contents;">
                    <div class="stars">
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-solid fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                        <i class="fa-regular fa-star"></i>
                    </div>
                    <span style="font-size: 13px; color: var(--gray-600);">e acima</span>
                </label>
            </div>
        </div>

        <input type="hidden" name="q" value="<?= htmlspecialchars($search_query) ?>">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by) ?>">
        
        <button type="submit" class="apply-filters-btn">
            <i class="fa-solid fa-filter"></i>
            Aplicar Filtros
        </button>
    </form>
</aside>