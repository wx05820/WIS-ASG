<?php
/**
 * Priority Detector Library
 * Automatically detects and assigns priority levels to contact messages
 * based on content analysis and keywords
 */

class PriorityDetector {
    
    // High priority keywords
    private static $high_priority_keywords = [
        'urgent', 'asap', 'immediately', 'emergency', 'critical', 'broken',
        'defective', 'damaged', 'not working', 'faulty', 'refund', 'return',
        'complaint', 'dissatisfied', 'unhappy', 'angry', 'frustrated',
        'cancel', 'cancellation', 'stop', 'cease', 'legal', 'lawyer',
        'sue', 'lawsuit', 'court', 'police', 'fraud', 'scam'
    ];
    
    // Medium priority keywords
    private static $medium_priority_keywords = [
        'question', 'inquiry', 'information', 'details', 'specification',
        'availability', 'stock', 'delivery', 'shipping', 'timeline',
        'schedule', 'appointment', 'meeting', 'discussion', 'proposal',
        'quote', 'pricing', 'cost', 'price', 'discount', 'offer',
        'upgrade', 'change', 'modify', 'custom', 'special'
    ];
    
    // Low priority keywords
    private static $low_priority_keywords = [
        'thanks', 'thank you', 'appreciation', 'compliment', 'praise',
        'feedback', 'suggestion', 'recommendation', 'tip', 'advice',
        'general', 'hello', 'hi', 'greeting', 'introduction'
    ];
    
    /**
     * Detect priority level based on message content
     * 
     * @param string $subject The message subject
     * @param string $message The message content
     * @param string $email The sender's email
     * @return string Priority level: 'high', 'medium', 'low'
     */
    public static function detectPriority($subject, $message, $email = '') {
        $content = strtolower($subject . ' ' . $message);
        
        // Check for high priority indicators
        $high_score = self::calculateScore($content, self::$high_priority_keywords);
        if ($high_score >= 2) {
            return 'high';
        }
        
        // Check for medium priority indicators
        $medium_score = self::calculateScore($content, self::$medium_priority_keywords);
        if ($medium_score >= 1) {
            return 'medium';
        }
        
        // Check for low priority indicators
        $low_score = self::calculateScore($content, self::$low_priority_keywords);
        if ($low_score >= 1) {
            return 'low';
        }
        
        // Default to medium if no clear indicators
        return 'medium';
    }
    
    /**
     * Calculate priority score based on keyword matches
     * 
     * @param string $content The content to analyze
     * @param array $keywords Array of keywords to search for
     * @return int The calculated score
     */
    private static function calculateScore($content, $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score++;
            }
        }
        return $score;
    }
    
    /**
     * Get priority color for display
     * 
     * @param string $priority The priority level
     * @return string CSS color class
     */
    public static function getPriorityColor($priority) {
        switch (strtolower($priority)) {
            case 'high':
                return 'priority-high';
            case 'medium':
                return 'priority-medium';
            case 'low':
                return 'priority-low';
            default:
                return 'priority-medium';
        }
    }
    
    /**
     * Get priority icon for display
     * 
     * @param string $priority The priority level
     * @return string Font Awesome icon class
     */
    public static function getPriorityIcon($priority) {
        switch (strtolower($priority)) {
            case 'high':
                return 'fas fa-exclamation-triangle';
            case 'medium':
                return 'fas fa-info-circle';
            case 'low':
                return 'fas fa-check-circle';
            default:
                return 'fas fa-info-circle';
        }
    }
    
    /**
     * Check if message should be escalated
     * 
     * @param string $priority The priority level
     * @param int $age_hours Age of message in hours
     * @return bool True if should be escalated
     */
    public static function shouldEscalate($priority, $age_hours) {
        switch (strtolower($priority)) {
            case 'high':
                return $age_hours > 2; // Escalate after 2 hours
            case 'medium':
                return $age_hours > 24; // Escalate after 24 hours
            case 'low':
                return $age_hours > 72; // Escalate after 72 hours
            default:
                return $age_hours > 24;
        }
    }
    
    /**
     * Get escalation message
     * 
     * @param string $priority The priority level
     * @param int $age_hours Age of message in hours
     * @return string Escalation message
     */
    public static function getEscalationMessage($priority, $age_hours) {
        $hours = floor($age_hours);
        $days = floor($age_hours / 24);
        
        if ($days > 0) {
            $time_str = $days . ' day' . ($days > 1 ? 's' : '');
        } else {
            $time_str = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        
        return "Message has been pending for {$time_str} and requires attention.";
    }
    
    /**
     * Analyze message sentiment (basic implementation)
     * 
     * @param string $content The message content
     * @return string Sentiment: 'positive', 'negative', 'neutral'
     */
    public static function analyzeSentiment($content) {
        $positive_words = ['good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic', 'love', 'happy', 'satisfied'];
        $negative_words = ['bad', 'terrible', 'awful', 'hate', 'angry', 'frustrated', 'disappointed', 'unsatisfied', 'poor'];
        
        $content_lower = strtolower($content);
        
        $positive_count = 0;
        $negative_count = 0;
        
        foreach ($positive_words as $word) {
            $positive_count += substr_count($content_lower, $word);
        }
        
        foreach ($negative_words as $word) {
            $negative_count += substr_count($content_lower, $word);
        }
        
        if ($positive_count > $negative_count) {
            return 'positive';
        } elseif ($negative_count > $positive_count) {
            return 'negative';
        } else {
            return 'neutral';
        }
    }
    
    /**
     * Get suggested response time based on priority
     * 
     * @param string $priority The priority level
     * @return int Suggested response time in hours
     */
    public static function getSuggestedResponseTime($priority) {
        switch (strtolower($priority)) {
            case 'high':
                return 2; // 2 hours
            case 'medium':
                return 24; // 24 hours
            case 'low':
                return 72; // 72 hours
            default:
                return 24;
        }
    }
    
    /**
     * Get response time target for display
     * 
     * @param string $priority The priority level
     * @return string Formatted response time target
     */
    public static function getResponseTimeTarget($priority) {
        $hours = self::getSuggestedResponseTime($priority);
        
        if ($hours < 24) {
            return $hours . 'h';
        } else {
            $days = floor($hours / 24);
            return $days . 'd';
        }
    }
    
    /**
     * Get priority description for tooltip
     * 
     * @param string $priority The priority level
     * @return string Priority description
     */
    public static function getPriorityDescription($priority) {
        switch (strtolower($priority)) {
            case 'high':
                return 'High Priority - Requires immediate attention';
            case 'medium':
                return 'Medium Priority - Standard response time';
            case 'low':
                return 'Low Priority - Can be handled during regular hours';
            case 'urgent':
                return 'Urgent - Critical issue requiring immediate response';
            default:
                return 'Standard Priority';
        }
    }
}
