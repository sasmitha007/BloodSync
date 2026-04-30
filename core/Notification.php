<?php
class Notification {
    
    public static function create($userId, $type, $title, $message, $metadata = null) {
        $sql = "INSERT INTO notifications (user_id, type, title, message, metadata) 
                VALUES (:user_id, :type, :title, :message, :metadata)";
        
        return Database::execute($sql, [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'metadata' => $metadata ? json_encode($metadata) : null
        ]);
    }
    
    public static function getUserNotifications($userId, $limit = 10) {
        $sql = "SELECT * FROM notifications 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        return Database::fetchAll($sql, [
            'user_id' => $userId,
            'limit' => $limit
        ]);
    }
    
    public static function markAsRead($notificationId, $userId) {
        $sql = "UPDATE notifications SET is_read = TRUE 
                WHERE id = :id AND user_id = :user_id";
        
        return Database::execute($sql, [
            'id' => $notificationId,
            'user_id' => $userId
        ]);
    }
    
    public static function getUnreadCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE user_id = :user_id AND is_read = FALSE";
        
        $result = Database::fetch($sql, ['user_id' => $userId]);
        return $result['count'] ?? 0;
    }
}
?>