<?php
/**
 * URL签名工具类
 * 用于生成带有时间戳和校验码的签名URL，并提供验证功能
 */
class UrlSigner {
    private $secret_key;
    private $expiry_hours = 48; // 默认过期时间为48小时
    
    /**
     * 构造函数
     * @param string $secret_key 用于签名的密钥
     * @param int $expiry_hours URL有效期（小时）
     */
    public function __construct($secret_key = null, $expiry_hours = null) {
        // 如果没有提供密钥，使用环境变量或默认值
        if ($secret_key === null) {
            $this->secret_key = env('URL_SIGNING_KEY', 'jianfa_default_signing_key_2024');
        } else {
            $this->secret_key = $secret_key;
        }
        
        // 如果提供了过期时间，则使用提供的值
        if ($expiry_hours !== null) {
            $this->expiry_hours = $expiry_hours;
        }
    }
    
    /**
     * 生成签名URL
     * @param string $url 原始URL
     * @return string 带签名的URL
     */
    public function signUrl($url) {
        // 生成过期时间戳（当前时间 + 过期小时数）
        $expires = time() + ($this->expiry_hours * 3600);
        
        // 解析URL
        $parsed_url = parse_url($url);
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        
        // 构建签名数据（路径 + 过期时间）
        $data_to_sign = $path . $expires;
        
        // 生成签名（使用HMAC SHA256）
        $signature = hash_hmac('sha256', $data_to_sign, $this->secret_key);
        
        // 构建新的URL查询参数
        $query = isset($parsed_url['query']) ? $parsed_url['query'] : '';
        $query = $query ? $query . '&' : '';
        $query .= "expires={$expires}&signature={$signature}";
        
        // 重建URL
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        
        return $scheme . $host . $port . $path . '?' . $query . $fragment;
    }
    
    /**
     * 验证签名URL
     * @param string $url 带签名的URL
     * @return bool 是否有效
     */
    public function validateSignedUrl($url) {
        // 解析URL
        $parsed_url = parse_url($url);
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        
        // 解析查询参数
        $query = [];
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query);
        }
        
        // 检查必要的参数是否存在
        if (!isset($query['expires']) || !isset($query['signature'])) {
            return false;
        }
        
        $expires = (int)$query['expires'];
        $provided_signature = $query['signature'];
        
        // 检查URL是否已过期
        if (time() > $expires) {
            return false;
        }
        
        // 重新计算签名
        $data_to_sign = $path . $expires;
        $expected_signature = hash_hmac('sha256', $data_to_sign, $this->secret_key);
        
        // 比较签名
        return hash_equals($expected_signature, $provided_signature);
    }
    
    /**
     * 从签名URL中提取原始URL
     * @param string $signed_url 带签名的URL
     * @return string 原始URL
     */
    public function getOriginalUrl($signed_url) {
        $parsed_url = parse_url($signed_url);
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        
        // 解析查询参数
        $query = [];
        if (isset($parsed_url['query'])) {
            parse_str($parsed_url['query'], $query);
        }
        
        // 移除签名相关参数
        unset($query['expires']);
        unset($query['signature']);
        
        // 重建查询字符串
        $new_query = !empty($query) ? '?' . http_build_query($query) : '';
        
        // 重建URL
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        
        return $scheme . $host . $port . $path . $new_query . $fragment;
    }
}
?>
