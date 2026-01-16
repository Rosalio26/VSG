<?php
/**
 * ================================================================================
 * VISIONGREEN - DIRETÓRIO DE PÁGINAS
 * Módulo: modules/pages/pages.php
 * Descrição: Listagem de todas as páginas e módulos acessíveis no sistema
 * ================================================================================
 */

if (!defined('IS_ADMIN_PAGE')) {
    require_once '../../../../registration/includes/db.php';
    session_start();
}

$adminRole = $_SESSION['auth']['role'] ?? 'admin';
$isSuperAdmin = ($adminRole === 'superadmin');

// Estrutura de páginas e módulos
$pages = [
    'Monitoramento' => [
        [
            'title' => 'Dashboard',
            'icon' => 'fa-gauge-high',
            'path' => 'modules/dashboard/dashboard',
            'description' => 'Visão geral do sistema'
        ],
        [
            'title' => 'Pendências',
            'icon' => 'fa-clipboard-check',
            'path' => 'modules/dashboard/pendencias',
            'description' => 'Registros aguardando aprovação'
        ],
        [
            'title' => 'Análise de Contas',
            'icon' => 'fa-magnifying-glass-chart',
            'path' => 'modules/dashboard/analise',
            'description' => 'Análise detalhada de contas'
        ],
        [
            'title' => 'Histórico',
            'icon' => 'fa-clock-rotate-left',
            'path' => 'modules/dashboard/historico',
            'description' => 'Histórico de operações'
        ],
        [
            'title' => 'Plataformas',
            'icon' => 'fa-layer-group',
            'path' => 'modules/dashboard/plataformas',
            'description' => 'Gestão de plataformas'
        ]
    ],
    'Comunicação' => [
        [
            'title' => 'Mensagens',
            'icon' => 'fa-comment-dots',
            'path' => 'modules/mensagens/mensagens',
            'description' => 'Inbox de mensagens'
        ]
    ],
    'Dados de Base' => [
        [
            'title' => 'Entrada de Dados',
            'icon' => 'fa-pen-to-square',
            'path' => 'modules/forms/form-input',
            'description' => 'Formulários de entrada'
        ],
        [
            'title' => 'Configurações de Formulários',
            'icon' => 'fa-gears',
            'path' => 'modules/forms/form-config',
            'description' => 'Configurar formulários'
        ],
        [
            'title' => 'Visão Geral (Tabelas)',
            'icon' => 'fa-list-check',
            'path' => 'modules/tabelas/tabela-geral',
            'description' => 'Tabela geral de dados'
        ],
        [
            'title' => 'Financeiro',
            'icon' => 'fa-money-bill-trend-up',
            'path' => 'modules/tabelas/tabela-financeiro',
            'description' => 'Dados financeiros'
        ],
        [
            'title' => 'Exportação',
            'icon' => 'fa-file-export',
            'path' => 'modules/tabelas/tabela-export',
            'description' => 'Exportar dados'
        ],
        [
            'title' => 'Lista de Empresas',
            'icon' => 'fa-building-user',
            'path' => 'modules/tabelas/lista-empresas',
            'description' => 'Listagem de empresas'
        ],
        [
            'title' => 'Relatórios',
            'icon' => 'fa-chart-line',
            'path' => 'modules/tabelas/relatorio',
            'description' => 'Gerar relatórios'
        ]
    ],
    'Administração' => array_merge(
        $isSuperAdmin ? [
            [
                'title' => 'Auditores',
                'icon' => 'fa-users-viewfinder',
                'path' => 'modules/auditor/auditor_lista',
                'description' => 'Listar todos os auditores',
                'admin_only' => true
            ],
            [
                'title' => 'Logs de Auditoria',
                'icon' => 'fa-file-signature',
                'path' => 'modules/auditor/auditor_logs',
                'description' => 'Visualizar logs de auditoria',
                'admin_only' => true
            ]
        ] : [],
        [
            [
                'title' => 'Usuários',
                'icon' => 'fa-users-gear',
                'path' => 'modules/usuarios/usuarios',
                'description' => 'Gestão de usuários'
            ]
        ]
    ),
    'Páginas' => [
        [
            'title' => 'Autenticação',
            'icon' => 'fa-shield-halved',
            'path' => 'modules/auditor/autenticacao',
            'description' => 'Logs de autenticação e segurança'
        ],
        [
            'title' => 'Perfil do Admin',
            'icon' => 'fa-id-badge',
            'path' => 'modules/auditor/perfil-admin',
            'description' => 'Gerenciar perfil administrativo'
        ],
        [
            'title' => 'Dados de Usuários',
            'icon' => 'fa-database',
            'path' => 'modules/auditor/dados-usuarios',
            'description' => 'Visualizar usuários e empresas'
        ],
        [
            'title' => 'Diretório de Páginas',
            'icon' => 'fa-file-invoice',
            'path' => 'modules/pages/pages',
            'description' => 'Ver todas as páginas'
        ]
    ],
    'Suporte Técnico' => array_merge(
        $isSuperAdmin ? [
            [
                'title' => 'Manual Superadmin',
                'icon' => 'fa-book-bookmark',
                'path' => 'modules/suporte/manual-admin',
                'description' => 'Documentação para superadministradores',
                'admin_only' => true
            ]
        ] : [],
        [
            [
                'title' => 'Ajuda',
                'icon' => 'fa-circle-question',
                'path' => 'modules/suporte/help-sub-admin',
                'description' => 'Centro de ajuda'
            ]
        ]
    ),
    'Sistema' => [
        [
            'title' => 'Definições',
            'icon' => 'fa-sliders',
            'path' => 'system/settings',
            'description' => 'Configurações do sistema'
        ]
    ]
];
?>

<!-- PAGE HEADER -->
<div style="margin-bottom: 30px;">
    <h2 style="color: #fff; margin: 0 0 8px 0;">
        <i class="fa-solid fa-file-invoice"></i>
        Diretório de Páginas
    </h2>
    <p style="color: #666; font-size: 0.85rem; margin: 0;">
        Navegue por todas as páginas e módulos disponíveis no sistema.
    </p>
</div>

<!-- SEARCH BAR -->
<div class="card mb-3">
    <div class="card-body">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fa-solid fa-magnifying-glass" style="color: #666;"></i>
            <input type="text" id="pagesSearch" class="form-input" placeholder="Pesquisar páginas..." style="margin: 0;">
        </div>
    </div>
</div>

<!-- PAGES GRID -->
<div id="pagesContainer" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 24px;">
    <?php foreach ($pages as $category => $categoryPages): ?>
        <?php foreach ($categoryPages as $page): ?>
            <div class="page-card" data-title="<?= htmlspecialchars(strtolower($page['title'])) ?>" data-category="<?= htmlspecialchars(strtolower($category)) ?>">
                <div style="
                    background: var(--bg-card);
                    border: 1px solid var(--border);
                    border-radius: 12px;
                    padding: 24px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                    display: flex;
                    flex-direction: column;
                    height: 100%;
                    position: relative;
                    overflow: hidden;
                " class="page-card-inner" onclick="loadContent('<?= $page['path'] ?>')">
                    
                    <!-- Gradient background -->
                    <div style="
                        position: absolute;
                        top: -50%;
                        right: -50%;
                        width: 200%;
                        height: 200%;
                        background: radial-gradient(circle, rgba(0,255,136,0.1) 0%, transparent 70%);
                        pointer-events: none;
                    "></div>

                    <!-- Icon -->
                    <div style="
                        width: 56px;
                        height: 56px;
                        border-radius: 12px;
                        background: rgba(0,255,136,0.1);
                        border: 1px solid rgba(0,255,136,0.2);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: var(--accent);
                        font-size: 1.75rem;
                        margin-bottom: 16px;
                        position: relative;
                        z-index: 1;
                    ">
                        <i class="fa-solid <?= $page['icon'] ?>"></i>
                    </div>

                    <!-- Content -->
                    <div style="position: relative; z-index: 1; flex: 1;">
                        <h3 style="
                            color: #fff;
                            font-size: 1.125rem;
                            font-weight: 700;
                            margin: 0 0 8px 0;
                        ">
                            <?= htmlspecialchars($page['title']) ?>
                        </h3>
                        <p style="
                            color: #666;
                            font-size: 0.875rem;
                            margin: 0;
                            line-height: 1.5;
                        ">
                            <?= htmlspecialchars($page['description']) ?>
                        </p>
                    </div>

                    <!-- Badge (if admin only) -->
                    <?php if (isset($page['admin_only']) && $page['admin_only']): ?>
                        <div style="
                            margin-top: 16px;
                            display: inline-flex;
                            align-items: center;
                            gap: 6px;
                            padding: 4px 10px;
                            background: rgba(255,204,0,0.1);
                            color: #ffcc00;
                            border-radius: 6px;
                            font-size: 0.7rem;
                            font-weight: 700;
                            text-transform: uppercase;
                            width: fit-content;
                            border: 1px solid rgba(255,204,0,0.2);
                            position: relative;
                            z-index: 1;
                        ">
                            <i class="fa-solid fa-shield"></i>
                            Superadmin
                        </div>
                    <?php endif; ?>

                    <!-- Arrow -->
                    <div style="
                        position: absolute;
                        bottom: 16px;
                        right: 16px;
                        width: 36px;
                        height: 36px;
                        border-radius: 8px;
                        background: rgba(0,255,136,0.1);
                        border: 1px solid rgba(0,255,136,0.2);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: var(--accent);
                        font-size: 1rem;
                        transition: all 0.3s ease;
                        z-index: 1;
                    " class="arrow-icon">
                        <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<!-- EMPTY STATE -->
<div id="emptyState" style="display: none; text-align: center; padding: 60px 20px;">
    <div style="font-size: 4rem; color: #555; margin-bottom: 20px;">
        <i class="fa-solid fa-inbox"></i>
    </div>
    <h3 style="color: #fff; margin-bottom: 10px;">Nenhuma página encontrada</h3>
    <p style="color: #666;">Tente ajustar sua busca</p>
</div>

<style>
    .card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .card-body {
        padding: 20px;
    }

    .mb-3 {
        margin-bottom: 20px;
    }

    .form-input {
        width: 100%;
        padding: 10px 12px;
        background: var(--bg-elevated);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(35, 134, 54, 0.1);
    }

    .page-card {
        animation: fadeIn 0.4s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .page-card-inner {
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .page-card-inner:hover {
        border-color: var(--accent);
        background: var(--bg-card);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        transform: translateY(-4px);
    }

    .page-card-inner:hover .arrow-icon {
        background: rgba(0,255,136,0.2);
        transform: translateX(4px);
    }

    .page-card.hidden {
        display: none;
    }
</style>

<script>
    const searchInput = document.getElementById('pagesSearch');
    const pagesContainer = document.getElementById('pagesContainer');
    const emptyState = document.getElementById('emptyState');
    const pageCards = document.querySelectorAll('.page-card');

    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        let visibleCount = 0;

        pageCards.forEach(card => {
            const title = card.dataset.title;
            const category = card.dataset.category;
            
            if (title.includes(query) || category.includes(query) || query === '') {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        // Show empty state if no results
        if (visibleCount === 0) {
            emptyState.style.display = 'block';
        } else {
            emptyState.style.display = 'none';
        }
    });

    // Keyboard shortcut: focus search
    document.addEventListener('keydown', function(e) {
        if (e.key === '/' && !searchInput.matches(':focus')) {
            e.preventDefault();
            searchInput.focus();
        }
    });
</script>