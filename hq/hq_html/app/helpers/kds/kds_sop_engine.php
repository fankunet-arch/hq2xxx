<?php
/**
 * KDS SOP Engine - business rules & code parsing
 * Revision: 6.0 (Template Parser Refactor)
 */

if (!class_exists('KdsSopParser')) {
    class KdsSopParser {
        private $pdo;
        private $store_id;
        private $rules = null; // 缓存规则

        public function __construct(PDO $pdo, int $store_id) {
            $this->pdo = $pdo;
            $this->store_id = $store_id;
        }

        /**
         * 加载此门店可用的所有SOP解析规则
         */
        private function loadRules(): void {
            if ($this->rules !== null) return;

            // 智能 SQL：优先拉取门店专属规则 (store_id DESC)，然后按优先级 (priority ASC)
            $sql = "
                SELECT * FROM kds_sop_query_rules
                WHERE is_active = 1
                  AND (store_id = :current_store_id OR store_id IS NULL)
                ORDER BY 
                  store_id DESC,  /* 确保门店专属规则 (e.g., 1) 排在全局规则 (NULL) 之前 */
                  priority ASC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':current_store_id' => $this->store_id]);
            $this->rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        /**
         * V1 解析器: "分隔符" 模式 (e.g., P-A-M-T) (已重命名)
         */
        private function _parseV1Delimiter(string $code, array $config): ?array {
            $format_str = $config['format'] ?? '';     // "P-A-M-T"
            $separator = $config['separator'] ?? '-'; // "-"
            $prefix = $config['prefix'] ?? '';       // "#"

            if (!empty($prefix)) {
                if (strpos($code, $prefix) !== 0) return null;
                $code = substr($code, strlen($prefix));
            }

            $format_parts = explode('-', $format_str);
            $code_parts = explode($separator, $code);

            if (count($format_parts) !== count($code_parts)) return null;

            $result = ['p' => '', 'a' => null, 'm' => null, 't' => null, 'ord' => null];
            foreach ($format_parts as $index => $part_key) {
                $part_key_lower = strtolower($part_key);
                $value = $code_parts[$index] ?? null;
                
                if (array_key_exists($part_key_lower, $result)) {
                    if ($value !== null && $value !== '') {
                        $result[$part_key_lower] = $value;
                    }
                }
            }
            
            return (!empty($result['p'])) ? $result : null;
        }

        /**
         * V1 解析器: "键值对" 模式 (e.g., ?o=101&c=1) (已重命名)
         */
        private function _parseV1KeyValue(string $code, array $config): ?array {
            $code = ltrim($code, '?#/');
            parse_str($code, $query_params);
            if (empty($query_params)) return null;

            $result = ['p' => '', 'a' => null, 'm' => null, 't' => null, 'ord' => null];
            $p_key = $config['P_key'] ?? 'p';
            $a_key = $config['A_key'] ?? '';
            $m_key = $config['M_key'] ?? '';
            $t_key = $config['T_key'] ?? '';

            if (empty($query_params[$p_key])) return null;
            
            $result['p'] = $query_params[$p_key] ?? '';
            if (!empty($a_key) && !empty($query_params[$a_key])) $result['a'] = $query_params[$a_key];
            if (!empty($m_key) && !empty($query_params[$m_key])) $result['m'] = $query_params[$m_key];
            if (!empty($t_key) && !empty($query_params[$t_key])) $result['t'] = $query_params[$t_key];
            
            return $result;
        }

        /**
         * [NEW] V2 解析器: "模板" 模式 (e.g., {ORD}|{P}-{A})
         */
        private function _parseTemplateV2(string $code, array $config): ?array {
            $template = $config['template'] ?? ''; // e.g., "?p={P}&c={A}"
            $mapping = $config['mapping'] ?? [];   // e.g., [ "p" => "P", "a" => "A" ]
            
            if (empty($template) || empty($mapping)) return null;

            // 1. 反转映射，从占位符 {P} 映射到标准键 'p'
            // [ "P" => "p", "A" => "a", ... ]
            $placeholder_to_key_map = [];
            foreach ($mapping as $key => $placeholder) {
                if (!empty($placeholder)) {
                    $placeholder_to_key_map[$placeholder] = $key;
                }
            }
            
            // 2. 将模板字符串转换为正则表达式
            $regex = '~^';
            $parts = preg_split('/(\{[a-zA-Z0-9_]+\})/', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
            $found_placeholders = [];
            
            foreach ($parts as $part) {
                if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $part, $matches)) {
                    // 这是一个占位符: {P}
                    $placeholder = $matches[1]; // "P"
                    if (isset($placeholder_to_key_map[$placeholder])) {
                        // 使用 "命名捕获组" (?P<name>...)
                        $regex .= '(?P<' . $placeholder . '>.+?)';
                        $found_placeholders[] = $placeholder;
                    } else {
                        // 模板中的 {XXX} 在 mapping 中未定义, 视为静态文本
                        $regex .= preg_quote($part);
                    }
                } else {
                    // 这是一个静态分隔符: "?p="
                    $regex .= preg_quote($part);
                }
            }
            $regex .= '$~u'; // 结尾, u=utf8

            // 3. 执行匹配
            if (!preg_match($regex, $code, $matches)) {
                return null; // 不匹配
            }

            // 4. 映射结果
            $result = ['p' => '', 'a' => null, 'm' => null, 't' => null, 'ord' => null];
            $p_found = false;

            foreach ($found_placeholders as $placeholder) { // e.g., "P", "A"
                $value = $matches[$placeholder] ?? null;
                $key = $placeholder_to_key_map[$placeholder]; // e.g., "p", "a"
                
                if ($key === 'p' && !empty($value)) {
                    $result['p'] = $value;
                    $p_found = true;
                } elseif (array_key_exists($key, $result) && !empty($value)) {
                    $result[$key] = $value;
                }
            }

            // P (产品) 必须存在
            return $p_found ? $result : null;
        }

        /**
         * 公共方法：解析查询码 (V2 重构)
         * @param string $raw_code 原始查询码 (来自 KDS JS 或扫码枪)
         * @return array|null 成功则返回 ['p'=>'101', 'a'=>'1', ...]，失败则 null
         */
        public function parse(string $raw_code): ?array {
            $this->loadRules();
            
            $raw_code = trim($raw_code);
            if ($raw_code === '') {
                return null;
            }

            foreach ($this->rules as $rule) {
                try {
                    $config = json_decode($rule['config_json'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("KDS SOP Parser: Skipping rule ID {$rule['id']} due to invalid JSON.");
                        continue; 
                    }

                    $result = null;
                    
                    // [V2] 优先使用新模板解析器
                    if (isset($config['template'])) {
                        $result = $this->_parseTemplateV2($raw_code, $config);
                    } 
                    // [V1] 兼容旧的 DELIMITER 规则
                    elseif ($rule['extractor_type'] === 'DELIMITER') {
                        $result = $this->_parseV1Delimiter($raw_code, $config);
                    } 
                    // [V1] 兼容旧的 KEY_VALUE 规则
                    elseif ($rule['extractor_type'] === 'KEY_VALUE') {
                        $result = $this->_parseV1KeyValue($raw_code, $config);
                    }
                    
                    // 如果此规则成功匹配
                    if ($result !== null) {
                        $result['raw'] = $raw_code;
                        return $result; // 立即返回第一个匹配项
                    }

                } catch (Throwable $e) {
                    // 捕获单个规则解析的错误，防止中断循环
                    error_log("KDS SOP Parser: Error in rule ID {$rule['id']}: " . $e->getMessage());
                }
            }

            // 遍历完所有规则都未匹配
            return null;
        }
    }
} // end !class_exists