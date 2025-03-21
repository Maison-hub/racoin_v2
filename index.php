<?php

require 'vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Controller\GetCategorie;
use Controller\GetDepartment;
use Controller\Index;
use Controller\Item;
use Database\Connection;
use Model\Annonce;
use Model\Annonceur;
use Model\Categorie;
use Model\Departement;
use Slim\Handlers\Strategies\RequestHandler;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Slim\Factory\AppFactory;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a logger instance
$logger = new Logger('app_logger');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/app.log', Logger::DEBUG));

// Initialisation de Slim
$app = AppFactory::create();

// Add the logger to the container
$container = $app->getContainer();
$container['logger'] = function ($c) use ($logger) {
    return $logger;
};

// Middleware to log requests
$logMiddleware = function (Request $request, RequestHandler $handler) use ($logger) {
    $logger->info('Request:', [
        'method' => $request->getMethod(),
        'uri' => (string)$request->getUri(),
        'headers' => $request->getHeaders(),
        'body' => (string)$request->getBody()
    ]);

    $response = $handler->handle($request);

    $logger->info('Response:', [
        'status' => $response->getStatusCode(),
        'headers' => $response->getHeaders(),
        'body' => (string)$response->getBody()
    ]);

    return $response;
};

// Add the middleware to the app
$app->add($logMiddleware);

Connection::createConn();

$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$app->addRoutingMiddleware();


// Initialisation de Twig
$loader = new FilesystemLoader(__DIR__ . '/template');
$twig = new Environment($loader);

if (!isset($_SESSION)) {
    session_start();
    $_SESSION['formStarted'] = true;
}

if (!isset($_SESSION['token'])) {
    $token = md5(uniqid(rand(), true));
    $_SESSION['token'] = $token;
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

$cat = new GetCategorie();
$dpt = new GetDepartment();

/**
 * @OA\Get(
 *     path="/",
 *     summary="Display all announcements",
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Annonce"))
 *     )
 * )
 */
$app->get('/', function (Request $request, Response $response) use ($twig, $menu, $chemin, $cat) {
    $index = new Index();
    $index->displayAllAnnonce($twig, $menu, $chemin, $cat->getCategories());
    return $response;
});

/**
 * @OA\Get(
 *     path="/item/{n}",
 *     summary="Display a specific announcement",
 *     @OA\Parameter(
 *         name="n",
 *         in="path",
 *         description="ID of the announcement",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Announcement not found"
 *     )
 * )
 */
$app->get('/item/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin, $cat) {
    $n     = $arg['n'];
    $item = new Item();
    $item->afficherItem($twig, $menu, $chemin, $n, $cat->getCategories());
    return $response;
});

/**
 * @OA\Get(
 *     path="/add",
 *     summary="Display the form to add an announcement",
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->get('/add', function (Request $request, Response $response) use ($twig, $app, $menu, $chemin, $cat, $dpt) {
    $ajout = new Controller\AddItem();
    $ajout->addItemView($twig, $menu, $chemin, $cat->getCategories(), $dpt->getAllDepartments());
    return $response;
});

/**
 * @OA\Post(
 *     path="/add",
 *     summary="Add an announcement",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->post('/add', function (Request $request, Response $response) use ($twig, $app, $menu, $chemin) {
    $allPostVars = $request->getParsedBody();
    $ajout = new Controller\AddItem();
    $ajout->addNewItem($twig, $menu, $chemin, $allPostVars);
    return $response;
});

/**
 * @OA\Get(
 *     path="/item/{id}/edit",
 *     summary="Display the form to edit an announcement",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID of the announcement",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->get('/item/{id}/edit', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin) {
    $id   = $arg['id'];
    $item = new Item();
    $item->modifyGet($twig, $menu, $chemin, $id);
    return $response;
});

/**
 * @OA\Post(
 *     path="/item/{id}/edit",
 *     summary="Edit an announcement",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID of the announcement",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->post('/item/{id}/edit', function (Request $request, Response $response, $arg) use ($twig, $app, $menu, $chemin, $cat, $dpt) {
    $id = $arg['id'];
    $allPostVars = $request->getParsedBody();
    $item = new Item();
    $item->modifyPost($twig, $menu, $chemin, $id, $allPostVars, $cat->getCategories(), $dpt->getAllDepartments());
    return $response;
});

/**
 * @OA\Get(
 *     path="/item/{id}/confirm",
 *     summary="Display the confirmation page for an announcement",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="ID of the announcement",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->map(['GET, POST'], '/item/{id}/confirm', function (Request $request, Response $response, $arg) use ($twig, $app, $menu, $chemin) {
    $id = $arg['id'];
    $allPostVars = $request->getParsedBody();
    $item = new Item();
    $item->edit($twig, $menu, $chemin, $id, $allPostVars);
    return $response;
});

/**
 * @OA\Get(
 *     path="/search",
 *     summary="Display the search page",
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->get('/search', function (Request $request, Response $response) use ($twig, $menu, $chemin, $cat) {
    $s = new Controller\Search();
    $s->show($twig, $menu, $chemin, $cat->getCategories());
    return $response;
});

/**
 * @OA\Post(
 *     path="/search",
 *     summary="Search for announcements",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->post('/search', function (Request $request, Response $response) use ($app, $twig, $menu, $chemin, $cat) {
    $array = $request->getParsedBody();
    $s = new Controller\Search();
    $s->research($array, $twig, $menu, $chemin, $cat->getCategories());
    return $response;
});

/**
 * @OA\Get(
 *     path="/annonceur/{n}",
 *     summary="Display the page of an advertiser",
 *     @OA\Parameter(
 *         name="n",
 *         in="path",
 *         description="ID of the advertiser",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonceur")
 *     )
 * )
 */
$app->get('/annonceur/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin, $cat) {
    $n = $arg['n'];
    $annonceur = new Controller\ViewAnnonceur();
    $annonceur->afficherAnnonceur($twig, $menu, $chemin, $n, $cat->getCategories());
    return $response;
});

/**
 * @OA\Get(
 *     path="/del/{n}",
 *     summary="Display the confirmation page to delete an announcement",
 *     @OA\Parameter(
 *         name="n",
 *         in="path",
 *         description="ID of the announcement",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->get('/del/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin) {
    $n = $arg['n'];
    $item = new Controller\Item();
    $item->supprimerItemGet($twig, $menu, $chemin, $n);
    return $response;
});

/**
 * @OA\Post(
 *     path="/del/{n}",
 *     summary="Delete an announcement",
 *     @OA\Parameter(
 *         name="n",
 *         in="path",
 *         description="ID of the announcement",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Annonce")
 *     )
 * )
 */
$app->post('/del/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin, $cat) {
    $n = $arg['n'];
    $item = new Controller\Item();
    $item->supprimerItemPost($twig, $menu, $chemin, $n, $cat->getCategories());
    return $response;
});

/**
 * @OA\Get(
 *     path="/cat/{n}",
 *     summary="Display the page of a category",
 *     @OA\Parameter(
 *         name="n",
 *         in="path",
 *         description="ID of the category",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Categorie")
 *     )
 * )
 */
$app->get('/cat/{n}', function (Request $request, Response $response, $arg) use ($twig, $menu, $chemin, $cat) {
    $n = $arg['n'];
    $categorie = new Controller\GetCategorie();
    $categorie->displayCategorie($twig, $menu, $chemin, $cat->getCategories(), $n);
    return $response;
});

/**
 * @OA\Get(
 *     path="/api",
 *     summary="Display the API page",
 *     @OA\Response(
 *         response=200,
 *         description="Successful response",
 *         @OA\JsonContent(ref="#/components/schemas/Api")
 *     )
 * )
 */
$app->get('/api(/)', function (Request $request, Response $response) use ($twig, $menu, $chemin, $cat) {
    $template = $twig->load('api.html.twig');
    $menu = array(
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

    /**
     * @OA\Get(
     *     path="/key",
     *     summary="Display the key generation page",
     *     @OA\Response(
     *     response=200,
     *     description="Successful response",
     *     @OA\JsonContent(ref="#/components/schemas/Key")
     *    )
     * )
     * @OA\Post(
     *     path="/key",
     *     summary="Generate a key",
     *     @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(ref="#/components/schemas/Key")
     *   ),
     *     @OA\Response(
     *     response=200,
     *     description="Successful response",
     *     @OA\JsonContent(ref="#/components/schemas/Key")
     *   )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/annonce/{id}",
     *     summary="Display a specific announcement",
     *     @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="ID of the announcement",
     *     required=true,
     *     @OA\Schema(type="integer")
     *  ),
     *     @OA\Response(
     *     response=200,
     *     description="Successful response",
     *     @OA\JsonContent(ref="#/components/schemas/Annonce")
     * ),
     */
    $app->get('/annonce/{id}[/]', function (Request $request, Response $response, $arg) use ($app) {
        $id = $arg['id'];
        $annonceList = ['id_annonce', 'id_categorie as categorie', 'id_annonceur as annonceur', 'id_departement as departement', 'prix', 'date', 'titre', 'description', 'ville'];
        $return = Annonce::select($annonceList)->find($id);

        if (isset($return)) {
            $response->headers->set('Content-Type', 'application/json');
            $return->categorie = Categorie::find($return->categorie);
            $return->annonceur = Annonceur::select('email', 'nom_annonceur', 'telephone')
                ->find($return->annonceur);
            $return->departement = Departement::select('id_departement', 'nom_departement')->find($return->departement);
            $links = [];
            $links['self']['href'] = '/api/annonce/' . $return->id_annonce;
            $return->links = $links;
            echo $return->toJson();
        } else {
            return $response->withStatus(404);
        }
        return $response;
    });

    /**
     * @OA\Get(
     *     path="/annonces",
     *     summary="Display all announcements",
     *     @OA\Response(
     *     response=200,
     *     description="Successful response",
     *     @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Annonce"))
     * ),
     */
    $app->get('/annonces[/]', function (Request $request, Response $response) use ($app) {
        $annonceList = ['id_annonce', 'prix', 'titre', 'ville'];
        $response->headers->set('Content-Type', 'application/json');
        $a = Annonce::all($annonceList);
        $links = [];
        foreach ($a as $ann) {
            $links['self']['href'] = '/api/annonce/' . $ann->id_annonce;
            $ann->links = $links;
        }
        $links['self']['href'] = '/api/annonces/';
        $a->links = $links;
        echo $a->toJson();
        return $response;
    });

    /**
     * @OA\Get(
     *     path="/categorie/{id}",
     *     summary="Display the page of a category",
     *     @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="ID of the category",
     *     required=true,
     *     @OA\Schema(type="integer")
     *  ),
     *     @OA\Response(
     *     response=200,
     *     description="Successful response",
     *     @OA\JsonContent(ref="#/components/schemas/Categorie")
     * ),
     */
    $app->get('/categorie/{id}[/]', function (Request $request, Response $response, $arg) use ($app) {
        $id = $arg['id'];
        $response->headers->set('Content-Type', 'application/json');
        $a = Annonce::select('id_annonce', 'prix', 'titre', 'ville')
            ->where('id_categorie', '=', $id)
            ->get();
        $links = [];

        foreach ($a as $ann) {
            $links['self']['href'] = '/api/annonce/' . $ann->id_annonce;
            $ann->links = $links;
        }

        $c = Categorie::find($id);
        $links['self']['href'] = '/api/categorie/' . $id;
        $c->links = $links;
        $c->annonces = $a;
        echo $c->toJson();
        return $response;
    });

    /**
     * @OA\Get(
     *     path="/categories",
     *     summary="Display all categories",
     *     @OA\Response(
     *     response=200,
     *     description="Successful response",
     *     @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Categorie"))
     * ),
     */
    $app->get('/categories[/]', function (Request $request, Response $response, $arg) use ($app) {
        $response->headers->set('Content-Type', 'application/json');
        $c = Categorie::get();
        $links = [];
        foreach ($c as $cat) {
            $links['self']['href'] = '/api/categorie/' . $cat->id_categorie;
            $cat->links = $links;
        }
        $links['self']['href'] = '/api/categories/';
        $c->links = $links;
        echo $c->toJson();
        return $response;
    });
});

$app->run();
