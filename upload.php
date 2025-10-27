<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

class GitHubUploader {
    private $token;
    private $user;
    private $repo;
    private $branch;
    private $baseUrl;
    
    public function __construct($token, $user, $repo, $branch = 'main') {
        $this->token = $token;
        $this->user = $user;
        $this->repo = $repo;
        $this->branch = $branch;
        $this->baseUrl = "https://api.github.com/repos/{$user}/{$repo}";
    }
    
    public function uploadZip($zipFile, $uploadType, $targetPath = '') {
        try {
            // Cek repository
            if (!$this->checkRepository()) {
                if (!$this->createRepository()) {
                    throw new Exception('Gagal membuat repository');
                }
            }
            
            if ($uploadType === 'zip-only') {
                // Upload ZIP saja
                return $this->uploadSingleFile($zipFile, $targetPath);
            } else {
                // Ekstrak dan upload individual files
                return $this->extractAndUpload($zipFile, $targetPath);
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function checkRepository() {
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->token,
                'User-Agent: PHP-Script',
                'Accept: application/vnd.github.v3+json'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }
    
    private function createRepository() {
        $url = "https://api.github.com/user/repos";
        $data = [
            'name' => $this->repo,
            'auto_init' => true,
            'private' => false
        ];
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->token,
                'User-Agent: PHP-Script',
                'Content-Type: application/json',
                'Accept: application/vnd.github.v3+json'
            ],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 201;
    }
    
    private function uploadSingleFile($zipFile, $targetPath) {
        $fileName = $targetPath ?: $zipFile['name'];
        $content = base64_encode(file_get_contents($zipFile['tmp_name']));
        
        $data = [
            'message' => 'Upload file ZIP: ' . $zipFile['name'],
            'content' => $content,
            'branch' => $this->branch
        ];
        
        return $this->githubApiCall("contents/{$fileName}", 'PUT', $data);
    }
    
    private function extractAndUpload($zipFile, $targetPath) {
        $zip = new ZipArchive();
        if ($zip->open($zipFile['tmp_name']) !== TRUE) {
            throw new Exception('Tidak dapat membuka file ZIP');
        }
        
        $results = [
            'success' => 0,
            'total' => $zip->numFiles,
            'files' => []
        ];
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $fileName = $fileInfo['name'];
            
            // Skip directories
            if (substr($fileName, -1) === '/') {
                continue;
            }
            
            // Baca konten file
            $fileContent = $zip->getFromIndex($i);
            $base64Content = base64_encode($fileContent);
            
            // Tentukan path
            $filePath = $targetPath ? "{$targetPath}/{$fileName}" : $fileName;
            
            // Upload ke GitHub
            $uploaded = $this->uploadFileToGitHub($filePath, $base64Content);
            
            if ($uploaded) {
                $results['success']++;
                $results['files'][] = [
                    'path' => $filePath,
                    'status' => 'Success'
                ];
            } else {
                $results['files'][] = [
                    'path' => $filePath,
                    'status' => 'Failed'
                ];
            }
        }
        
        $zip->close();
        return $results;
    }
    
    private function uploadFileToGitHub($filePath, $content) {
        // Cek apakah file sudah ada
        $sha = null;
        $checkResponse = $this->githubApiCall("contents/{$filePath}?ref={$this->branch}", 'GET');
        
        if (isset($checkResponse['sha'])) {
            $sha = $checkResponse['sha'];
        }
        
        $data = [
            'message' => 'Add ' . $filePath . ' from ZIP extraction',
            'content' => $content,
            'branch' => $this->branch
        ];
        
        if ($sha) {
            $data['sha'] = $sha;
        }
        
        $response = $this->githubApiCall("contents/{$filePath}", 'PUT', $data);
        return isset($response['content']);
    }
    
    private function githubApiCall($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . '/' . $endpoint;
        
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $this->token,
                'User-Agent: PHP-Script',
                'Accept: application/vnd.github.v3+json',
                'Content-Type: application/json'
            ]
        ];
        
        if ($method === 'POST' || $method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = $method;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $user = $_POST['user'] ?? '';
    $repo = $_POST['repo'] ?? '';
    $branch = $_POST['branch'] ?? 'main';
    $uploadType = $_POST['uploadType'] ?? 'zip-only';
    $targetPath = $_POST['targetPath'] ?? '';
    
    if (empty($token) || empty($user) || empty($repo)) {
        echo json_encode(['success' => false, 'error' => 'Token, user, dan repo diperlukan']);
        exit;
    }
    
    if (!isset($_FILES['zipFile'])) {
        echo json_encode(['success' => false, 'error' => 'File ZIP diperlukan']);
        exit;
    }
    
    $uploader = new GitHubUploader($token, $user, $repo, $branch);
    $result = $uploader->uploadZip($_FILES['zipFile'], $uploadType, $targetPath);
    
    echo json_encode($result);
} else {
    echo json_encode(['success' => false, 'error' => 'Method tidak diizinkan']);
}
?>