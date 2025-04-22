<?php
/**
 * CloudPRO - Класс для работы с базой данных
 */

class Database extends PDO {
    /**
     * Конструктор класса базы данных
     * 
     * @param string $host Хост базы данных
     * @param string $dbname Имя базы данных
     * @param string $username Имя пользователя
     * @param string $password Пароль
     */
    public function __construct($host, $dbname, $username, $password) {
        try {
            parent::__construct("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            logMessage('Подключение к базе данных установлено', 'info');
        } catch (PDOException $e) {
            logMessage('Ошибка подключения к базе данных: ' . $e->getMessage(), 'error');
            die('Ошибка подключения к базе данных. Подробности в логе.');
        }
    }
    
    /**
     * Выполнение SQL запроса
     * 
     * @param string $sql SQL запрос
     * @param array $params Параметры запроса
     * @return array Результат запроса
     */
    public function query($sql, $params = []) {
        try {
            $stmt = parent::prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            logMessage('Ошибка SQL запроса: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Получение одной записи
     * 
     * @param string $sql SQL запрос
     * @param array $params Параметры запроса
     * @return array|bool Запись или false
     */
    public function queryOne($sql, $params = []) {
        try {
            $stmt = parent::prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            logMessage('Ошибка SQL запроса: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Получение значения из одной ячейки
     * 
     * @param string $sql SQL запрос
     * @param array $params Параметры запроса
     * @return mixed Значение ячейки
     */
    public function queryScalar($sql, $params = []) {
        try {
            $stmt = parent::prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            logMessage('Ошибка SQL запроса: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Выполнение запроса без возврата результата
     * 
     * @param string $sql SQL запрос
     * @param array $params Параметры запроса
     * @return bool Успешность выполнения
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = parent::prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            logMessage('Ошибка SQL запроса: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Получение последнего вставленного ID
     * 
     * @return int ID
     */
    public function lastId() {
        return parent::lastInsertId();
    }
    
    /**
     * Начало транзакции
     * 
     * @return bool Успех
     */
    public function startTransaction() {
        return parent::beginTransaction();
    }
    
    /**
     * Фиксация транзакции
     * 
     * @return bool Успех
     */
    public function commitTransaction() {
        return parent::commit();
    }
    
    /**
     * Откат транзакции
     * 
     * @return bool Успех
     */
    public function rollbackTransaction() {
        return parent::rollBack();
    }
} 