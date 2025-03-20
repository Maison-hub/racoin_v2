<?php
require 'vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Controller\getCategorie;
use Controller\getDepartment;
use Controller\index;
use Controller\item;
use Database\Connection;
use Model\Annonce;
use Model\Annonceur;
use Model\Categorie;
use Model\Departement;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Slim\Factory\AppFactory;




// Initialisation de Slim
$app = AppFactory::create();

Connection::createConn();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$app->addRoutingMiddleware();


// Initialisation de Twig
$loader = new FilesystemLoader(__DIR__ . '/template');
$twig   = new Environment($loader);

// Ajout d'un middleware pour le trailing slash
//$app->add(function (Request $request, $handler) {
//    $uri = $request->getUri();
//    $path = $uri->getPath();
//    if ($path != '/' && str_ends_with($path, '/')) {
//        $uri = $uri->withPath(substr($path, 0, -1));
//        if ($request->getMethod() == 'GET') {
//            $response = new \Slim\Psr7\Response();
//            return $response->withHeader('Location', (string)$uri)->withStatus(301);
//        }
//    }
//    return $handler->handle($request);
//});


if (!isset($_SESSION)) {
    session_start();
    $_SESSION['formStarted'] = true;
}

if (!isset($_SESSION['token'])) {
    $token                  = md5(uniqid(rand(), TRUE));
    $_SESSION['token']      = $token;
    $_SESSION['token_time'] = time();
} else {
    $token = $_SESSION['token'];
}

$menu = [
    [
        'href' => './index.php',
        'text' => 'Accueil'
    ]
];

$chemin = dirname($_SERVER['SCRIPT_NAME']);

$cat = new getCategorie();
$dpt = new getDepartment();

$app->get('/', function (Request $request, Response $response) use ($twig, $menu, $chemin, $cat) {
    $index = new index();
    $index->displayAllAnnonce($twig, $menu, $chemin, $cat->getCategories());
    return $response;
});

$app->get('/item/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin, $cat) {
    $n     = $arg['n'];
    $item = new item();
    $item->afficherItem($twig, $menu, $chemin, $n, $cat->getCategories());
    return $response;

});

$app->get('/add', function (Request $request, Response $response) use ($twig, $app, $menu, $chemin, $cat, $dpt) {
    $ajout = new Controller\addItem();
    $ajout->addItemView($twig, $menu, $chemin, $cat->getCategories(), $dpt->getAllDepartments());
    return $response;
});

$app->post('/add', function (Request $request, Response $response) use ($twig, $app, $menu, $chemin) {
    $allPostVars = $request->getParsedBody();
    $ajout       = new Controller\addItem();
    $ajout->addNewItem($twig, $menu, $chemin, $allPostVars);
    return $response;
});

$app->get('/item/{id}/edit', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin) {
    $id   = $arg['id'];
    $item = new item();
    $item->modifyGet($twig, $menu, $chemin, $id);
    return $response;
});

$app->post('/item/{id}/edit', function (Request $request, Response $response, $arg) use ($twig, $app, $menu, $chemin, $cat, $dpt) {
    $id          = $arg['id'];
    $allPostVars = $request->getParsedBody();
    $item        = new item();
    $item->modifyPost($twig, $menu, $chemin, $id, $allPostVars, $cat->getCategories(), $dpt->getAllDepartments());
    return $response;
});

$app->map(['GET, POST'], '/item/{id}/confirm', function (Request $request, Response $response, $arg) use ($twig, $app, $menu, $chemin) {
    $id   = $arg['id'];
    $allPostVars = $request->getParsedBody();
    $item        = new item();
    $item->edit($twig, $menu, $chemin, $id, $allPostVars);
    return $response;
});

$app->get('/search', function (Request $request, Response $response) use ($twig, $menu, $chemin, $cat) {
    $s = new Controller\Search();
    $s->show($twig, $menu, $chemin, $cat->getCategories());
    return $response;
});


$app->post('/search', function (Request $request, Response $response) use ($app, $twig, $menu, $chemin, $cat) {
    $array = $request->getParsedBody();
    $s     = new Controller\Search();
    $s->research($array, $twig, $menu, $chemin, $cat->getCategories());
    return $response;
});

$app->get('/annonceur/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin, $cat) {
    $n         = $arg['n'];
    $annonceur = new Controller\viewAnnonceur();
    $annonceur->afficherAnnonceur($twig, $menu, $chemin, $n, $cat->getCategories());
    return $response;
});

$app->get('/del/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin) {
    $n    = $arg['n'];
    $item = new Controller\item();
    $item->supprimerItemGet($twig, $menu, $chemin, $n);
    return $response;
});

$app->post('/del/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin, $cat) {
    $n    = $arg['n'];
    $item = new Controller\item();
    $item->supprimerItemPost($twig, $menu, $chemin, $n, $cat->getCategories());
    return $response;
});

$app->get('/cat/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin, $cat) {
    $n = $arg['n'];
    $categorie = new Controller\getCategorie();
    $categorie->displayCategorie($twig, $menu, $chemin, $cat->getCategories(), $n);
    return $response;
});

$app->get('/api(/)', function (Request $request, Response $response) use ($twig, $menu, $chemin, $cat) {
    $template = $twig->load('api.html.twig');
    $menu     = array(
        array(
            'href' => $chemin,
            'text' => 'Acceuil'
        ),
        array(
            'href' => $chemin . '/api',
            'text' => 'Api'
        )
    );
    echo $template->render(array('breadcrumb' => $menu, 'chemin' => $chemin));
    return $response;
});

$app->group('/api', function () use ($app, $twig, $menu, $chemin, $cat) {

    $app->map(['GET', 'POST'], '/key', function (Request $request, Response $response) use ($twig, $menu, $chemin, $cat) {
        $kg = new Controller\KeyGenerator();
        if ($request->getMethod() === 'POST') {
            $nom = $request->getParsedBody()['nom'] ?? '';
            $kg->generateKey($twig, $menu, $chemin, $cat->getCategories(), $nom);
        } else {
            $kg->show($twig, $menu, $chemin, $cat->getCategories());
        }
        return $response;
    });

    $app->get('/annonce/{id}[/]', function (Request $request, Response $response, $arg) use ($app) {
        $id          = $arg['id'];
        $annonceList = ['id_annonce', 'id_categorie as categorie', 'id_annonceur as annonceur', 'id_departement as departement', 'prix', 'date', 'titre', 'description', 'ville'];
        $return      = Annonce::select($annonceList)->find($id);

        if (isset($return)) {
            $response->headers->set('Content-Type', 'application/json');
            $return->categorie     = Categorie::find($return->categorie);
            $return->annonceur     = Annonceur::select('email', 'nom_annonceur', 'telephone')
                ->find($return->annonceur);
            $return->departement   = Departement::select('id_departement', 'nom_departement')->find($return->departement);
            $links                 = [];
            $links['self']['href'] = '/api/annonce/' . $return->id_annonce;
            $return->links         = $links;
            echo $return->toJson();
        } else {
            return $response->withStatus(404);
        }
        return $response;
    });


    $app->get('/annonces[/]', function (Request $request, Response $response) use ($app) {
        $annonceList = ['id_annonce', 'prix', 'titre', 'ville'];
        $response->headers->set('Content-Type', 'application/json');
        $a     = Annonce::all($annonceList);
        $links = [];
        foreach ($a as $ann) {
            $links['self']['href'] = '/api/annonce/' . $ann->id_annonce;
            $ann->links            = $links;
        }
        $links['self']['href'] = '/api/annonces/';
        $a->links              = $links;
        echo $a->toJson();
        return $response;
    });



    $app->get('/categorie/{id}[/]', function (Request $request, Response $response, $arg) use ($app) {
        $id = $arg['id'];
        $response->headers->set('Content-Type', 'application/json');
        $a     = Annonce::select('id_annonce', 'prix', 'titre', 'ville')
            ->where('id_categorie', '=', $id)
            ->get();
        $links = [];

        foreach ($a as $ann) {
            $links['self']['href'] = '/api/annonce/' . $ann->id_annonce;
            $ann->links            = $links;
        }

        $c                     = Categorie::find($id);
        $links['self']['href'] = '/api/categorie/' . $id;
        $c->links              = $links;
        $c->annonces           = $a;
        echo $c->toJson();
        return $response;
    });

    $app->get('/categories[/]', function (Request $request, Response $response, $arg) use ($app) {
        $response->headers->set('Content-Type', 'application/json');
        $c     = Categorie::get();
        $links = [];
        foreach ($c as $cat) {
            $links['self']['href'] = '/api/categorie/' . $cat->id_categorie;
            $cat->links            = $links;
        }
        $links['self']['href'] = '/api/categories/';
        $c->links              = $links;
        echo $c->toJson();
        return $response;
    });


//    $app->get('/key', function () use ($app, $twig, $menu, $chemin, $cat) {
//        $kg = new Controller\KeyGenerator();
//        $kg->show($twig, $menu, $chemin, $cat->getCategories());
//    });
//
//    $app->post('/key', function () use ($app, $twig, $menu, $chemin, $cat) {
//        $nom = $_POST['nom'];
//
//        $kg = new Controller\KeyGenerator();
//        $kg->generateKey($twig, $menu, $chemin, $cat->getCategories(), $nom);
//    });

});

//$routes = $app->getRouteCollector()->getRoutes();
//foreach ($routes as $route) {
//    $methods = implode(', ', $route->getMethods());
//    $pattern = $route->getPattern();
//    $callable = is_object($route->getCallable()) ? get_class($route->getCallable()) : (string)$route->getCallable();
//    echo "Pattern: $pattern, Methods: $methods, Callable: $callable" . PHP_EOL . '<br>';
//}

$app->run();
