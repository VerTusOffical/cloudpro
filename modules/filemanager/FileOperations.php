<?php

class FileOperations {
    public function isPathSafe($path) {
        $realPath = realpath($path);
        
        if ($realPath === false) {
            return false;
        }
        
        $allowedPaths = [
            '/var/www',
            '/etc/nginx',
            '/var/log',
            APP_PATH
        ];
        
        foreach ($allowedPaths as $allowedPath) {
            if (strpos($realPath, $allowedPath) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    public function isValidFilename($filename) {
        return preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename) && $filename !== '.' && $filename !== '..';
    }

    public function getDirectoryContents($path) {
        if (!is_dir($path) || !is_readable($path)) {
            return [];
        }
        
        $items = [];
        $handle = opendir($path);
        
        if ($handle) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                
                $fullPath = $path . '/' . $entry;
                
                $item = [
                    'name' => $entry,
                    'path' => $fullPath,
                    'is_dir' => is_dir($fullPath),
                    'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                    'modified' => filemtime($fullPath),
                    'permissions' => substr(sprintf('%o', fileperms($fullPath)), -3)
                ];
                
                $items[] = $item;
            }
            
            closedir($handle);
        }
        
        usort($items, function($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) {
                return -1;
            } elseif (!$a['is_dir'] && $b['is_dir']) {
                return 1;
            } else {
                return strcasecmp($a['name'], $b['name']);
            }
        });
        
        return $items;
    }
    

    public function uploadFile($file, $path) {
        if (!is_dir($path) || !is_writable($path)) {
            return [
                'success' => false,
                'error' => 'Директория не существует или недоступна для записи'
            ];
        }
        
        $filename = $file['name'];
        $targetPath = $path . '/' . $filename;
        
        if (!$this->isValidFilename($filename)) {
            return [
                'success' => false,
                'error' => 'Недопустимое имя файла'
            ];
        }
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            chmod($targetPath, 0644);
            
            return [
                'success' => true,
                'filename' => $filename
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Ошибка при загрузке файла'
        ];
    }
    

    public function createFolder($path, $folderName) {
        if (!is_dir($path) || !is_writable($path)) {
            return [
                'success' => false,
                'error' => 'Директория не существует или недоступна для записи'
            ];
        }
        
        $folderPath = $path . '/' . $folderName;
        
        if (file_exists($folderPath)) {
            return [
                'success' => false,
                'error' => 'Директория уже существует'
            ];
        }
        
        if (mkdir($folderPath, 0755)) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Ошибка при создании директории'
        ];
    }
    

    public function createFile($path, $fileName, $content) {
        if (!is_dir($path) || !is_writable($path)) {
            return [
                'success' => false,
                'error' => 'Директория не существует или недоступна для записи'
            ];
        }
        
        $filePath = $path . '/' . $fileName;
        
        if (file_exists($filePath)) {
            return [
                'success' => false,
                'error' => 'Файл уже существует'
            ];
        }
        
        if (file_put_contents($filePath, $content) !== false) {
            chmod($filePath, 0644);
            
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Ошибка при создании файла'
        ];
    }
    

    public function rename($path, $oldName, $newName) {
        $oldPath = $path . '/' . $oldName;
        $newPath = $path . '/' . $newName;
        
        if (!file_exists($oldPath)) {
            return [
                'success' => false,
                'error' => 'Файл или директория не существует'
            ];
        }
        
        if (file_exists($newPath)) {
            return [
                'success' => false,
                'error' => 'Файл или директория с таким именем уже существует'
            ];
        }
        

        if (rename($oldPath, $newPath)) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Ошибка при переименовании'
        ];
    }
    
    public function delete($path, $name, $isDir) {
        $fullPath = $path . '/' . $name;
        
        if (!file_exists($fullPath)) {
            return [
                'success' => false,
                'error' => 'Файл или директория не существует'
            ];
        }
        
        if ($isDir) {
            $result = $this->deleteDirRecursive($fullPath);
        } else {
            $result = unlink($fullPath);
        }
        
        if ($result) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Ошибка при удалении'
        ];
    }
    
    private function deleteDirRecursive($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirRecursive($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    public function saveFile($filePath, $content) {
        if (!file_exists($filePath) || !is_file($filePath)) {
            return [
                'success' => false,
                'error' => 'Файл не существует'
            ];
        }
        
        if (!is_writable($filePath)) {
            return [
                'success' => false,
                'error' => 'Файл недоступен для записи'
            ];
        }
        
        if (file_put_contents($filePath, $content) !== false) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Ошибка при сохранении файла'
        ];
    }
    

    public function downloadFile($filePath) {
        if (!file_exists($filePath) || !is_file($filePath) || !is_readable($filePath)) {
            die('Файл не существует или недоступен для чтения');
        }
        
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $fileSize);
        
        readfile($filePath);
        exit;
    }
    

    public function changePermissions($path, $permissions) {
        if (!file_exists($path)) {
            return [
                'success' => false,
                'error' => 'Файл или директория не существует'
            ];
        }
        
        $mode = octdec($permissions);
        
        if (chmod($path, $mode)) {
            return [
                'success' => true
            ];
        }
        
        return [
            'success' => false,
            'error' => 'Ошибка при изменении прав доступа'
        ];
    }
    
    public function getDirSize($path) {
        $size = 0;
        
        if (!is_dir($path) || !is_readable($path)) {
            return $size;
        }
        
        $handle = opendir($path);
        
        if ($handle) {
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                
                $fullPath = $path . '/' . $entry;
                
                if (is_dir($fullPath)) {
                    $size += $this->getDirSize($fullPath);
                } else {
                    $size += filesize($fullPath);
                }
            }
            
            closedir($handle);
        }
        
        return $size;
    }
} 