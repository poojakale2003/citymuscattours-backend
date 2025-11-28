<?php

require_once __DIR__ . '/BaseModel.php';

class User extends BaseModel {
    protected $table = 'users';
    protected $primaryKey = 'id';

    public function findByEmail($email) {
        return $this->findOne(['email' => strtolower(trim($email))]);
    }

    public function createUser($data) {
        $data['email'] = strtolower(trim($data['email']));
        $data['password'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        $data['role'] = $data['role'] ?? 'user';
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->create($data);
    }

    public function getById($id) {
        return $this->findById($id);
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function addRefreshToken($userId, $token, $expiresAt) {
        $hashed = hash('sha256', $token);
        $stmt = $this->db->prepare("
            INSERT INTO refresh_tokens (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$userId, $hashed, $expiresAt]);
    }

    public function removeRefreshToken($userId, $token) {
        $hashed = hash('sha256', $token);
        $stmt = $this->db->prepare("
            DELETE FROM refresh_tokens 
            WHERE user_id = ? AND token = ?
        ");
        return $stmt->execute([$userId, $hashed]);
    }

    public function findRefreshToken($userId, $token) {
        $hashed = hash('sha256', $token);
        $stmt = $this->db->prepare("
            SELECT * FROM refresh_tokens 
            WHERE user_id = ? AND token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$userId, $hashed]);
        return $stmt->fetch();
    }

    public function purgeExpiredTokens($userId) {
        $stmt = $this->db->prepare("
            DELETE FROM refresh_tokens 
            WHERE user_id = ? AND expires_at < NOW()
        ");
        return $stmt->execute([$userId]);
    }
}

