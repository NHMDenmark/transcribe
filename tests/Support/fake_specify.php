<?php

/*
 * A very small stand-in for a Specify 7 server, for the tests of the sync. Run with the built-in web server:
 *
 *   FAKE_SPECIFY_STATE=/path/state.json php -S 127.0.0.1:PORT tests/Support/fake_specify.php
 *
 * The state file (JSON) is written by the test and read and updated here:
 *   objects        the records Specify holds (a search matches on their catalognumber, id or text1 field)
 *   reject_login   true: the login is refused
 *   reject_collections  collection ids the login refuses (the session, if open, stays in its collection)
 *   put_code       the HTTP code answered to a PUT (default 200)
 *   schema         the schemas answered to /api/specify_schema/openapi.json (table => {properties: {field: {type}}})
 *   datamodel      the list answered to /context/datamodel.json (tables with their relationships)
 *   collections    collection id => its discipline (uri), answered to GET /api/specify/collection/<id>/
 *   require_discipline  true: a PUT that creates a collecting event without a discipline answers the 500 of Specify
 *   resources      path => the object answered to a GET of it (an independent related object linked by its address)
 *   extra          fields added to every object of 'specimens' (a collection, an embedded collecting event...)
 *   logged         the collection the session is logged in to (null = not logged in)
 *   logins         every login received: [{username, collection}]
 *   puts           every PUT received: [{uri, body}]
 *   gets           every search (GET of a table) received: the request address
 *   posts          every POST received (an object created): [{table, body}]
 */

$stateFile = getenv('FAKE_SPECIFY_STATE');
$state = json_decode((string) file_get_contents($stateFile), true) ?: [];
$save = function () use (&$state, $stateFile) {
    file_put_contents($stateFile, json_encode($state));
};

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = [];
parse_str((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $query);
$body = (string) file_get_contents('php://input');

header('Content-Type: application/json');

if ($path === '/context/login/' && $method === 'GET') {
    header('Set-Cookie: csrftoken=fake-token; Path=/');
    echo '{}';
} elseif ($path === '/context/login/' && $method === 'PUT') {
    $data = json_decode($body, true) ?: [];
    $state['logins'][] = ['username' => $data['username'] ?? null, 'collection' => $data['collection'] ?? null];
    if (! empty($state['reject_login']) || in_array($data['collection'] ?? null, $state['reject_collections'] ?? [], true)) {
        http_response_code(403);
        echo '{"detail":"refused"}';
    } else {
        $state['logged'] = $data['collection'] ?? 0;
        echo '{}';
    }
    $save();
} elseif ($path === '/context/user.json') {
    if (array_key_exists('logged', $state) && $state['logged'] !== null && empty($state['reject_login'])) {
        echo json_encode(['name' => 'tester', 'available_collections' => [
            ['resource_uri' => '/api/specify/collection/4/', 'discipline' => '/api/specify/discipline/1/'],
        ]]);
    } else {
        echo '{}';
    }
} elseif ($path === '/api/specify_schema/openapi.json') {
    echo json_encode(['components' => ['schemas' => $state['schema'] ?? new stdClass()]]);
} elseif ($path === '/context/datamodel.json') {
    echo json_encode($state['datamodel'] ?? []);
} elseif (isset($state['resources'][$path]) && $method === 'GET') {
    echo json_encode($state['resources'][$path]);
} elseif (preg_match('#^/api/specify/collection/(\d+)/$#', $path, $m) && $method === 'GET') {
    $discipline = $state['collections'][$m[1]] ?? null;
    if ($discipline === null) { http_response_code(404); echo '{}'; } else { echo json_encode(['id' => (int) $m[1], 'resource_uri' => $path, 'discipline' => $discipline]); }
} elseif (preg_match('#^/api/specify/(\w+)/$#', $path, $m) && $method === 'GET') {
    $state['gets'][] = $_SERVER['REQUEST_URI'];
    $save();
    $found = [];
    foreach ($state['objects'] ?? [] as $object) {
        if (isset($query['catalognumber__in'])) {
            $match = in_array($object['catalognumber'] ?? null, explode(',', $query['catalognumber__in']), true);
        } elseif (isset($query['catalognumber'])) {
            $match = ($object['catalognumber'] ?? null) === $query['catalognumber'];
        } elseif (isset($query['text1'])) {
            $match = ($object['text1'] ?? null) === $query['text1'];
        } elseif (isset($query['id'])) {
            $match = (string) ($object['id'] ?? '') === $query['id'];
        } else {
            $match = false;
        }
        if ($match) {
            $found[] = $object;
        }
    }
    echo json_encode(['objects' => $found, 'meta' => ['total_count' => count($found)]]);
} elseif (preg_match('#^/api/specify/\w+/\d+/$#', $path) && $method === 'PUT') {
    $put = json_decode($body, true);
    $state['puts'][] = ['uri' => $path, 'body' => $put];
    $save();
    // like Specify: an object created from a nested value needs its discipline (a collecting event has one)
    if (! empty($state['require_discipline']) && is_array($put['collectingevent'] ?? null) && empty($put['collectingevent']['resource_uri']) && empty($put['collectingevent']['discipline'])) {
        http_response_code(500);
        echo '{"exception":"IntegrityError","message":"1048","data":"Column \'DisciplineID\' cannot be null"}';
        return;
    }
    http_response_code((int) ($state['put_code'] ?? 200));
    echo ($state['put_code'] ?? 200) >= 300 ? '{"message":"Specify says no"}' : $body;
} elseif (preg_match('#^/api/specify/(\w+)/$#', $path, $m) && $method === 'POST') {
    $post = json_decode($body, true) ?: [];
    $state['posts'][] = ['table' => $m[1], 'body' => $post];
    $id = 900 + count($state['posts']);
    $save();
    http_response_code(201);
    echo json_encode(array_merge($post, ['id' => $id, 'resource_uri' => '/api/specify/' . $m[1] . '/' . $id . '/']));
} else {
    http_response_code(404);
    echo '{"detail":"not found: ' . $method . ' ' . $path . '"}';
}
