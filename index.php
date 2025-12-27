<?php
    require_once __DIR__ . '/registration/bootstrap.php';
    require_once __DIR__ . '/registration/includes/security.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagina inicial VSG</title>
    
    <link rel="stylesheet" href="assets/style/geral.css">
    <link rel="stylesheet" href="assets/style/mobile_screen_landing_page.css">
</head>
<body>
    <header id="headerBloc">
        <ul>
            <li class="content-logo">
                <a href="index.html" class="home_logo">
                    <img src="sources/img/logo_small_wh.png" alt="Logo">
                    <span class="link-lg">vision green</span>
                </a>
            </li>
            <li>
                <ul class="content-link-ass">
                    <li class="ass-link-hed"><a href="" class="link-header link-lg">Seja</a></li>
                    <li class="ass-link-hed"><a href="" class="link-header link-lg">Mais</a></li>
                    <li class="ass-link-hed"><a href="" class="link-header link-lg">Verde</a></li>
                </ul>
            </li>
        </ul>
    </header>
    <main>
        <section class="block-mn fr-mn">
            <section class="col-sld-cmp fr-col-land">
                <div class="asp-col">
                    <div class="colo-vsg-wel">
                        <img class="img-logo" src="sources/img/logo_bing_gr.png" alt="Imagem da VSG">
                        <h1 class="vsg-tlt-txt">Vision Green</h1>
                        <p class="vsg-par-txt">sustentando um futuro verde</p>
                    </div>
                    <div class="arb-th-cmp">
                        <p class="fin-txt-mn">Encotre, escolhe e venda em diversos cantos do mundo. <br> Onde estiveres, estaras sempre no verde.</p>
                        <section class="block-mn camp-btn-reg add-gap-1">
                            <form method="post" action="registration/process/start.php">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" id="btn-cadastrar" class="regi-btn sign-up-btn">
                                    cadastrar-se
                                </button>
                            </form>
                            <span class="th-opt-txt">ou</span>
                            <button class="regi-btn login-btn">login</button>
                        </section>
                    </div>
                </div>
            </section>

            <section class="col-sld-cmp sc-col-land">
                <div class="cnt-by-img">
                    <h1>Vision <span style="color: var(--color-bg-104);">Green</span></h1>
                    <p>Você pode comprar, vender produtos sustentaveis.</p>
                    <div class="col-land">
                        <div class="cnt-int-sc">
                            <h2>
                                <img src="sources/img/globo.png" alt="Globo Icon">
                                <span>Mercado Global</span>
                            </h2>
                            <p>Conecte-se com compradores e vendedores eco-conscientes de todo o mundo</p>
                        </div>
                        <div class="cnt-int-sc">
                            <h2>
                                <img src="sources/img/cargo-truck.png" alt="Truck Icon">
                                <span>Frete neutro em carbono</span>
                            </h2>
                            <p>Compensamos todas as actividades de preveção ao meio ambiente para minimizar o impacto ambiental</p>
                        </div>
                        <div class="cnt-int-sc">
                            <h2>
                                <img src="sources/img/eco.png" alt="Eco Icon">
                                <span>100% Eco Amigável</span>
                            </h2>
                            <p>Todos os produtos cumprem rigorosos critérios ambientais e de sustentabilidade</p>
                        </div>
                        <div class="cnt-int-sc">
                            <h2>
                                <img src="sources/img/app.png" alt="community Icon">
                                <span>Comunidade Dirigida</span>
                            </h2>
                            <p>Join a thriving community of environmentally conscious individuals</p>
                        </div>
                    </div>
                </div>
                <div class="cont-img-2-sl">
                    <img class="small-img" src="sources/img/vsg_icon/Joined img.png" alt="">
                </div>
            </section>
            

            <section class="col-sld-cmp tr-col-land">
                <div class="fil-cnt-tr">
                    <div class="scol-img">
                        <img src="sources/img/vsg_icon/bsiness_green.png" alt="">
                    </div>
                    <div class="al-tx">
                        <h1>Construindo um futuro mais verde juntos</h1>
                        <p>Vision Green é mais do que apenas um mercado - somos uma comunidade dedicada a tornar a vida sustentável e acessível para todos.<br>Pequenas mudanças podem fazer uma grande diferença.</p>
                    </div>
                </div>
                <div class="set-fil-tr">
                    <div class="col-fit-our">
                        <img src="sources/img/target_wh.png" alt="Icon de missão">
                        <h2>Nossa Missão</h2>
                        <p>Criar um mercado sustentável que conecte consumidores conscientes com produtos ecológicos em todo o mundo</p>
                    </div>
                    <div class="col-fit-our sc">
                        <img src="sources/img/eye_wh.png" alt="Icon de olho">
                        <h2>Nossa Visão</h2>
                        <p>Um mundo onde a vida sustentável é a norma, e cada compra contribui para um planeta mais saudável</p>
                    </div>
                    <div class="col-fit-our">
                        <img src="sources/img/eco1.png" alt="icon de valores">
                        <h2>Nossos Valores</h2>
                        <p>Sustentabilidade, transparência, comunidade e qualidade estão no centro de tudo o que fazemos</p>
                    </div>
                </div>
            </section>

            <section class="col-sld-cmp fo-col-land">
                <div class="col-sld-fo fo-col-sld">
                    <div class="col-bl-fo-ld cnt-fo-sld">
                        <p class="fo-tx-p1"><span><img src="sources/img/logo_small_wh.png" alt="Logo"></span> Compras sustentáveis</p>
                        <h1>Descubra produtos <span>ecológicos</span> em todo o mundo</h1>
                        <p class="fo-tx-p2">Junte-se ao Vision Green marketplace - onde a sustentabilidade encontra conveniência. Compre diversos produtos ecológicos  verificados em todo o mundo.</p>
                    </div>
                    <div class="col-bl-fo-ld cnt-sec-sld"></div>
                </div>

                <div class="col-sld-fo sc-col-sld">
                    <div class="cnt-fo-sld-sc">
                        <li class="content-logo">
                            <a href="index.html" class="home_logo">
                                <img src="sources/img/logo_small_wh.png" alt="Logo">
                                <span id="ur-logo" class="link-lg">vision green</span>
                            </a>
                        </li>
                        <p>Seu mercado confiável para produtos sustentáveis e ecológicos de todo o mundo</p>
                    </div>
                    <div class="cnt-fo-sld-sc">
                        <h2>Loja</h2>
                        <ul>
                            <li>Todos os produtos</li>
                            <li>Cozinha e Jantar</li>
                            <li>Eletrônica</li>
                            <li>Esportes e Fitness</li>
                            <li>Acessórios</li>
                        </ul>
                    </div>
                    <div class="cnt-fo-sld-sc">
                        <h2>Empresa</h2>
                        <ul>
                            <li>Sobre nós</li>
                            <li>Nossa missão</li>
                            <li>Sustentabilidade</li>
                            <li>Carreiras</li>
                            <li>Contato</li>
                        </ul>
                    </div>
                    <div class="cnt-fo-sld-sc boletim">
                        <h2>Boletim informativo</h2>
                        <p>Inscreva-se para receber atualizações sobre novos produtos ecológicos e ofertas especiais.</p>
                        <form action="">
                            <input type="email" name="" id="">
                            <button type="submit"><img src="sources/img/empty-email_wh.png" alt="Icon email"></button>
                        </form>
                    </div>
                </div>
                <div class="fouter-co">
                    <div class="copy-tx"> &copy;<span class="ano"></span> Vision Green - Mercado Sunstetavel</div>
                    <div class="pry-tx">
                        <a href="#">Termos e condições</a>
                        <a href="#">Politica de Privacidade</a>
                        <a href="#">Cookie Policy</a>
                    </div>
                    <p>by Group Layout <img src="sources/img/logo_gl.png" alt="GL icon"></p>
                </div>
            </section>
        </section>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Seleciona o botão de login
            const loginBtn = document.querySelector('.login-btn');

            if (loginBtn) {
                // 2. Escuta o clique
                loginBtn.addEventListener('click', function() {
                    // 3. Define o destino (Caminho para o seu arquivo login.php)
                    const destino = 'registration/login/login.php';

                    // 4. Transforma a ação em um link de redirecionamento
                    window.location.href = destino;
                });
            }
        });
    </script>
</body>
</html>