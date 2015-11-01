<?php

function getDB()
{
    $dbhost = "localhost";
    $dbuser = "root";
    $dbpass = "";
    $dbname = "ecomhack";
    
    $mysql_conn_string = "mysql:host=$dbhost;dbname=$dbname";
    $dbConnection = new PDO($mysql_conn_string, $dbuser, $dbpass); 
    $dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbConnection;
}

function getLastPos($db) {
    $sth = $db->prepare("SELECT 
        x,
        y,
        TIMESTAMPDIFF(SECOND, ts, NOW()) AS ts
    FROM 
        locations
    ORDER BY 
        id DESC 
    LIMIT 
        1");
    
    $sth->execute();
    
    return $sth->fetch();
}

function distance($lat1, $lon1, $lat2, $lon2) {

    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;

    return $miles * 1.609344 * 1000;
}

require 'vendor/autoload.php';

$app = new \Slim\Slim();

$app->get('/', function() use($app) {
    $app->response->setStatus(200);
    echo "Welcome to watchr";
}); 

$app->get('/getBeacons', function () {
 
    $app = \Slim\Slim::getInstance();
 
    try 
    {
        $db = getDB();
 
        $sth = $db->prepare("SELECT * 
            FROM beacons
            ;");
 
        $sth->bindParam(':id', $id, PDO::PARAM_INT);
        $sth->execute();
 
        $student = $sth->fetchAll(PDO::FETCH_ASSOC);
 
        if($student) {
            $app->response->setStatus(200);
            $app->response()->headers->set('Content-Type', 'application/json');
            echo json_encode($student);
            $db = null;
        } else {
            throw new PDOException('No records found.');
        }
 
    }
catch(PDOException $e) {
  $app->response()->setStatus(404);
  echo '{"error":{"text":'. $e->getMessage() .'}}';
}
});

//Retrives all the product from an area. If 0 then all products
$app->get('/getProductsArea/:area', function ($area) {
    
    $client_id = 'HOgYyYrY85Rxc2Q_-yYI-mCs';
    $client_secret = '9Fpk_dCzPT9oWzl3sitJ3ekdcmZYG-mh';
    $project_key = 'test-40abc';
    $app = \Slim\Slim::getInstance();
    
    function makeRequest($url, $context) {
    $fp = fopen($url, 'rb', false, $context);
    if (!$fp) {
        throw new Exception("Problem with $url");
    }
    // get the response and decode
    $response = stream_get_contents($fp);
    if ($response === false) {
        throw new Exception("Problem reading data from $url");
    }
    $result = json_decode($response, true);
    // close the response
    fclose($fp);

    return $result;
}

// Request AccessToken
    $authUrl = "https://$client_id:$client_secret@auth.sphere.io/oauth/token";
    $data = array("grant_type" => "client_credentials", "scope" => "manage_project:$project_key");
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
    );
    $context = stream_context_create($options);
    $authResult = makeRequest($authUrl, $context);
    $access_token = $authResult["access_token"];
    
    // Fetch products
    if(area==0)
    $productUrl = "https://api.sphere.io/$project_key/product-projections/search?staged=true";
    else
    $productUrl = "https://api.sphere.io/$project_key/product-projections/search?staged=true&filter=variants.attributes.zone%3A".$area;
    $options = array(
        'http' => array(
            'header'  => "Authorization: Bearer $access_token",
            'method'  => 'GET'
        ),
    );
    $c = stream_context_create($options);
    $result = makeRequest($productUrl, $c);
    
    header('Content-Type: application/json');
    echo json_encode($result);

  
});


$app->post('/addPosition', function() use($app) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $x = !empty($data['lat']) ? $data['lat'] : 0;
    $y = !empty($data['lon']) ? $data['lon'] : 0;
    
    $stayed = 0;
 
    try 
    {
        $db = getDB();
        
        $lastPos = getLastPos($db);
        
        $distance = distance($lastPos['x'], $lastPos['y'], $x, $y);
        if ($distance < 2 && $lastPos['ts'] >= 1) {
            $sth = $db->prepare("INSERT INTO 
                locations (`x`,`y`,`stayed`,ts)
                VALUES    (:x, :y, :stayed, NOW());");
     
            $sth->bindParam(':x', $x, PDO::PARAM_STR);
            $sth->bindParam(':y', $y, PDO::PARAM_STR);
            $sth->bindParam(':stayed', $stayed, PDO::PARAM_INT);
            $sth->execute();
     
            $app->response->setStatus(200);
            $app->response()->headers->set('Content-Type', 'application/json');
            echo json_encode(array("status" => "success", "code" => $distance));
        }
        
        $db = null;
    } catch(PDOException $e) {
        $app->response()->setStatus(404);
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
 
}); 

$app->get('/getLast10Positions', function() {
    $app = \Slim\Slim::getInstance();
 
    try 
    {
        $db = getDB();
 
    $sth = $db->prepare("SELECT 
        x,
        y,
        TIMESTAMPDIFF(SECOND, ts, NOW()) AS ts
    FROM 
        locations
    ORDER BY 
        id DESC 
    LIMIT 
        10");
    
    $sth->execute();
    
    $result = $sth->fetchAll();
 
        if ($result) {
            $app->response->setStatus(200);
            $app->response()->headers->set('Content-Type', 'application/json');
            echo json_encode($result);
            $db = null;
        } else {
            throw new PDOException('No records found.');
        }
 
    }
    catch(PDOException $e) {
        $app->response()->setStatus(404);
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
});

$app->get('/getLastPosition', function () {
 
    $app = \Slim\Slim::getInstance();
 
    try 
    {
        $db = getDB();
 
        $student = getLastPos($db);
 
        if ($student) {
            $app->response->setStatus(200);
            $app->response()->headers->set('Content-Type', 'application/json');
            echo json_encode([$student['x'], $student['y']]);
            $db = null;
        } else {
            throw new PDOException('No records found.');
        }
 
    }
catch(PDOException $e) {
  $app->response()->setStatus(404);
  echo '{"error":{"text":'. $e->getMessage() .'}}';
}
});

$app->run();