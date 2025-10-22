<?php
ini_set('default_charset', 'UTF-8');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Bufferiser toute sortie accidentelle pour éviter de corrompre les réponses JSON
if (ob_get_level() === 0) ob_start();

// Configuration du log d'API
class APILogger {
    private static $instance = null;
    private $logFile;
    
    private function __construct() {
        $this->logFile = __DIR__ . '/api_debug.log';
        
        // Rotation des logs si > 5Mo
        if (file_exists($this->logFile) && filesize($this->logFile) > 5 * 1024 * 1024) {
            rename($this->logFile, $this->logFile . '.' . date('Y-m-d'));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function log($level, $message, $context = []) {
        if ((!defined('APP_DEBUG') || !APP_DEBUG) && $level === 'DEBUG') return;
        
        $log = sprintf(
            "[%s] [%s] %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );
        
        file_put_contents($this->logFile, $log, FILE_APPEND);
    }
}

$logger = APILogger::getInstance();
$logger->log('INFO', 'Nouvelle requête API', [
    'action' => $_GET['action'] ?? 'none',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
]);

// Vérifier config
if (!file_exists('config.php')) {
    $logger->log('ERROR', "ERREUR: config.php manquant");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration manquante']);
    exit;
}

try {
    require_once 'config.php';
    require_once 'database.php';
    require_once 'functions.php';
    require_once 'auth.php';
} catch (Exception $e) {
    $logger->log('ERROR', "ERREUR require: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur chargement: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

// Récupérer les données POST/PUT
$rawInput = file_get_contents('php://input');
$input = [];
if ($rawInput) {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $input = $decoded;
    } else {
        $logger->log('ERROR', "ERREUR JSON decode: " . json_last_error_msg());
    }
}

$logger->log('DEBUG', "Input data: " . json_encode($input));

try {
    switch ($action) {
        
        // ========== STATISTIQUES ==========
        case 'get_stats':
            $data = getStatistiques();
            jsonResponse(['success' => true, 'data' => $data]);
            break;
            
        // ========== CONDUCTEURS ==========
        case 'get_conducteurs':
            $data = getConducteurs();
            jsonResponse(['success' => true, 'data' => $data]);
            break;
            
        case 'add_conducteur':
            $id = addConducteur($input);
            jsonResponse(['success' => true, 'id' => $id], 201);
            break;
            
        case 'update_conducteur':
            if (!isset($input['id'])) {
                throw new Exception('ID manquant pour update_conducteur');
            }
            updateConducteur($input['id'], $input);
            jsonResponse(['success' => true, 'message' => 'Conducteur mis à jour']);
            break;
            
        case 'delete_conducteur':
            if (!isset($input['id'])) {
                throw new Exception('ID manquant pour delete_conducteur');
            }
            deleteConducteur($input['id']);
            jsonResponse(['success' => true, 'message' => 'Conducteur supprimé']);
            break;
            
        // ========== TOURNÉES ==========
        case 'get_tournees':
            $data = getTournees();
            jsonResponse(['success' => true, 'data' => $data]);
            break;
            
        case 'add_tournee':
            $id = addTournee($input);
            jsonResponse(['success' => true, 'id' => $id], 201);
            break;
            
        case 'update_tournee':
            if (!isset($input['id'])) {
                throw new Exception('ID manquant pour update_tournee');
            }
            updateTournee($input['id'], $input);
            jsonResponse(['success' => true, 'message' => 'Tournée mise à jour']);
            break;
            
        case 'delete_tournee':
            if (!isset($input['id'])) {
                throw new Exception('ID manquant pour delete_tournee');
            }
            deleteTournee($input['id']);
            jsonResponse(['success' => true, 'message' => 'Tournée supprimée']);
            break;
            
        // ========== PLANNING ==========
        case 'get_planning':
            $debut = $_GET['debut'] ?? date('Y-m-d');
            $fin = $_GET['fin'] ?? date('Y-m-d');
            $logger->log('DEBUG', "get_planning: debut=$debut, fin=$fin");
            $data = getPlanning($debut, $fin);
            jsonResponse(['success' => true, 'data' => $data]);
            break;
            
        case 'add_attribution':
            $logger->log('DEBUG', "add_attribution input: " . json_encode($input));
            
            // Validation stricte
            if (!isset($input['date'])) {
                throw new Exception('Date manquante');
            }
            if (!isset($input['periode'])) {
                throw new Exception('Période manquante');
            }
            if (!isset($input['tournee_id'])) {
                throw new Exception('Tournée ID manquante');
            }
            
            // Appel de la fonction
            $result = addAttribution($input);
            $logger->log('DEBUG', "add_attribution result: " . ($result ? 'success' : 'failed'));
            
            jsonResponse([
                'success' => true, 
                'message' => 'Attribution enregistrée',
                'score' => $input['score_ia'] ?? 0
            ]);
            break;
            
        case 'delete_attribution':
            $id = $input['id'] ?? $_GET['id'] ?? 0;
            if (!$id) {
                throw new Exception('ID manquant pour delete_attribution');
            }
            deleteAttribution($id);
            jsonResponse(['success' => true, 'message' => 'Attribution supprimée']);
            break;
            
        // ========== IA & SCORING ==========
        case 'calculer_score':
            $conducteurId = $_GET['conducteur_id'] ?? 0;
            $tourneeId = $_GET['tournee_id'] ?? 0;
            $date = $_GET['date'] ?? date('Y-m-d');
            $periode = $_GET['periode'] ?? 'matin';
            
            $logger->log('DEBUG', "calculer_score: c=$conducteurId, t=$tourneeId, d=$date, p=$periode");
            
            if (!$conducteurId || !$tourneeId) {
                throw new Exception('Paramètres manquants pour calculer_score');
            }
            
            $score = calculerScoreConducteur($conducteurId, $tourneeId, $date, $periode);
            $logger->log('DEBUG', "Score calculé: " . json_encode($score));
            
            jsonResponse(['success' => true, 'data' => $score]);
            break;
            
        case 'get_performance':
            $conducteurId = $_GET['conducteur_id'] ?? 0;
            $debut = $_GET['debut'] ?? date('Y-m-01');
            $fin = $_GET['fin'] ?? date('Y-m-t');
            
            if (!$conducteurId) {
                jsonResponse(['success' => true, 'data' => ['score_moyen' => 0, 'nb_attributions' => 0]]);
                break;
            }
            
            $perf = getPerformanceConducteur($conducteurId, $debut, $fin);
            jsonResponse(['success' => true, 'data' => $perf]);
            break;

        // ========== UTILISATEURS ==========
        case 'get_users':
            // Renvoie la liste des utilisateurs (admin seulement)
            $users = getUsers();
            jsonResponse(['success' => true, 'data' => $users]);
            break;

        case 'get_user':
            $id = $_GET['id'] ?? $input['id'] ?? null;
            if (!$id) jsonResponse(['success' => false, 'error' => 'ID manquant'], 400);
            $userData = getUserById($id);
            jsonResponse(['success' => true, 'data' => $userData]);
            break;

        case 'add_user':
            $newId = createUser($input);
            jsonResponse(['success' => true, 'id' => $newId], 201);
            break;

        case 'update_user':
            if (!isset($input['id'])) throw new Exception('ID manquant pour update_user');
            updateUser($input['id'], $input);
            jsonResponse(['success' => true, 'message' => 'Utilisateur mis à jour']);
            break;

        case 'delete_user':
            $id = $input['id'] ?? $_GET['id'] ?? 0;
            if (!$id) throw new Exception('ID manquant pour delete_user');
            deleteUser($id);
            jsonResponse(['success' => true, 'message' => 'Utilisateur supprimé']);
            break;

        case 'get_profile':
            // Retourne le profil de l'utilisateur connecté
            if (!isset($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Non authentifié'], 401);
            $profile = getUserById($_SESSION['user']['id']);
            jsonResponse(['success' => true, 'data' => $profile]);
            break;

        case 'update_profile':
            if (!isset($_SESSION['user'])) jsonResponse(['success' => false, 'error' => 'Non authentifié'], 401);
            $uid = $_SESSION['user']['id'];
            updateUser($uid, $input);
            jsonResponse(['success' => true, 'message' => 'Profil mis à jour']);
            break;
            
        case 'remplir_auto':
            $debut = $input['debut'] ?? $_GET['debut'] ?? date('Y-m-d');
            $fin = $input['fin'] ?? $_GET['fin'] ?? date('Y-m-d');
            $logger->log('DEBUG', "remplir_auto: debut=$debut, fin=$fin");
            $res = remplirPlanningAuto($debut, $fin);
            jsonResponse(['success' => true, 'data' => $res]);
            break;
            
        case 'get_score_global':
            $debut = $_GET['debut'] ?? date('Y-m-d', strtotime('monday this week'));
            $fin = $_GET['fin'] ?? date('Y-m-d', strtotime('sunday this week'));
            $score = getScorePerformanceGlobal($debut, $fin);
            jsonResponse(['success' => true, 'data' => $score]);
            break;
            
        // ========== CONFIGURATION ==========
        case 'get_config':
            $cle = $_GET['cle'] ?? null;
            $data = getConfig($cle);
            jsonResponse(['success' => true, 'data' => $data]);
            break;
            
        case 'set_config':
            foreach ($input as $cle => $valeur) {
                setConfig($cle, $valeur);
            }
            jsonResponse(['success' => true, 'message' => 'Configuration mise à jour']);
            break;
            
        // ========== ACTION INCONNUE ==========
        default:
            $logger->log('WARNING', "Action inconnue: $action");
            jsonResponse(['success' => false, 'error' => "Action '$action' inconnue"], 404);
    }
    
} catch (Exception $e) {
    $logger->log('ERROR', "EXCEPTION: " . $e->getMessage());
    $logger->log('ERROR', "Trace: " . $e->getTraceAsString());
    
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
