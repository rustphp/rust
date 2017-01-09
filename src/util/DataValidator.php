<?php
namespace rust\util;
//数据验证
final class DataValidator {
    const LOWER_CASE_LETTERS = 1;
    const CAPITAL_LETTERS    = 2;
    const LETTERS            = 3;
    const LETTERS_INTEGER    = 4;
    const CHARACTERS         = 5;
    private static $_initialized = FALSE;
    private static $_rules, $_msg;

    /**
     * regex pattern matched
     *
     * @param string $value
     * @param string $pattern
     * @return bool
     */
    public static function isMatched($value, $pattern) {
        $preg_result = preg_match($pattern, $value);
        if (!$preg_result) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * initialize
     */
    protected static function initialized() {
        if (self::$_initialized === TRUE) {
            return;
        }
        self::$_msg = [
            'required'     => '%s不能为空',
            'email'        => '请输入正确的邮箱,如cjj8110@163.com',
            'qq'           => '请输入正确的QQ号',
            'tel'          => '请输入正确的电话,如010-1234567',
            'mobile'       => '请输入正确的手机号',
            'number'       => '%s只能输入数字',
            'integer'      => '只能输入整数',
            'datetime'     => '请输入正确的日期或时间,如2016-5-20 05:02:00, 2016-5-20, 05:02:00',
            'same'         => '请确认输入的%s是否一致',
            'email-mobile' => '只能输入邮箱或手机号',
            'chars'        => function ($type) {
                if (DataValidator::LOWER_CASE_LETTERS === $type) {
                    return '请输入小写字母';
                }
                if (DataValidator::CAPITAL_LETTERS === $type) {
                    return '请输入大写字母';
                }
                if (DataValidator::LETTERS === $type) {
                    return '只能输入字母';
                } else {
                    if (DataValidator::LETTERS_INTEGER === $type) {
                        return '请输入字母或数字';
                    }
                }
                return '只能输入字母、数字及符号,如(~!@#$...等)';
            },
            'length'       => function ($min = NULL, $max = NULL) {
                if ($min !== NULL && $max !== NULL) {
                    return '只能输入' . $min . '至' . $max . '个字符';
                }
                if ($min !== NULL) {
                    return '至少输入' . $min . '个字符';
                }
                return '最多只能输入' . $max . '个字符';
            },
            'range'        => function ($min = NULL, $max = NULL) {
                if ($min !== NULL && $max !== NULL) {
                    return '%s不能少于' . $min . '且不能超过' . $max;
                }
                if ($min !== NULL) {
                    return '%s不能少于' . $min;
                }
                return '%s不能超过' . $max;
            },
        ];
        self::$_rules = [
            'email'        => '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
            'qq'           => '/[1-9][0-9]{4,}/',
            'tel'          => '/^\d{3,4}-?\d{7,8}$/',
            'mobile'       => '/^1[34578]{1}\d{9}$/',
            'number'       => '/^[\-\+]?\d*\.?\d*$/',
            'integer'      => '/^\d+$/',
            'chars'        => function ($value, $type) {
                $pattern = '/^[a-zA-Z\d\~\!\@\#\$\%\&\*\(\)\-\_\:\?]+$/';
                if (DataValidator::LOWER_CASE_LETTERS === $type) {
                    $pattern = '/^[a-z]+$/';
                } else {
                    if (DataValidator::CAPITAL_LETTERS === $type) {
                        $pattern = '/^[A-Z]+$/';
                    } else {
                        if (DataValidator::LETTERS === $type) {
                            $pattern = '/^[a-zA-Z]+$/';
                        } else {
                            if (DataValidator::LETTERS_INTEGER === $type) {
                                $pattern = '/^[a-zA-Z\d]+$/';
                            }
                        }
                    }
                }
                return DataValidator::isMatched($value, $pattern);
            },
            'required'     => function ($value) {
                if (is_array($value) && !$value) {
                    return FALSE;
                }
                if (is_array($value)) {
                    return TRUE;
                }
                if (strlen($value) < 1) {
                    return FALSE;
                }
                return TRUE;
            },
            'length'       => function ($value, $min_len = NULL, $max_len = NULL) {
                $v_len = strlen($value);
                if ($min_len !== NULL && $v_len < $min_len) {
                    return FALSE;
                }
                if ($max_len !== NULL && $v_len > $max_len) {
                    return FALSE;
                }
                return TRUE;
            },
            'datetime'     => function ($value, $format = 'Y-m-d H:i:s') {
                if (date($format, strtotime($value)) != $value) {
                    return FALSE;
                }
                return TRUE;
            },
            'same'         => function ($value, $value2) {
                if ($value != $value2) {
                    return FALSE;
                }
                return TRUE;
            },
            'range'        => function ($value, $max = NULL, $min = NULL) {
                if ($min !== NULL && $value < $min) {
                    return FALSE;
                }
                if ($max !== NULL && $value > $max) {
                    return FALSE;
                }
                return TRUE;
            },
            'email-mobile' => function ($value) {
                $email_rule = DataValidator::getRule('email');
                $is_email = DataValidator::isMatched($value, $email_rule);
                if ($is_email) {
                    return TRUE;
                }
                $mobile_rule = DataValidator::getRule('mobile');
                $is_mobile = DataValidator::isMatched($value, $mobile_rule);
                if ($is_mobile) {
                    return TRUE;
                }
                return FALSE;
            },
        ];
        self::$_initialized = TRUE;
    }

    /**
     * get validate rule
     *
     * @param string $rule_name
     * @return null
     */
    public static function getRule($rule_name) {
        self::initialized();
        if (!isset(self::$_rules[$rule_name])) {
            return NULL;
        }
        return self::$_rules[$rule_name];
    }

    /**
     * form validator
     *
     * @param array $elements
     * @param array $validate_rules
     *
     * @return array
     */
    public static function validate($elements, $validate_rules) {
        self::initialized(); //初始化
        $result = [];
        foreach ($validate_rules as $element_name => $rules) {
            $rule_msg = isset($rules['msg']) ? $rules['msg'] : [];
            unset($rules['msg']);
            $is_required = in_array('required', $rules) ? TRUE : FALSE;
            if (!isset($elements[$element_name]) || !$rules || !is_array($rules)) {
                $rule_names = is_array($rules) ? array_keys($rules) : [];
                $rule_name = $rule_names ? array_shift($rule_names) : NULL;
                $paras = $rule_name ? $rules[$rule_name] : []; //['H:i:s']
                $validate_msg = self::valueValidate(NULL, $rule_name, $paras, $is_required);
                if ($validate_msg) {
                    $result[$element_name] = vsprintf($validate_msg, $rule_msg);
                }
                continue;
            }
            foreach ($rules as $rule_name => $paras) {
                $validate_msg = self::valueValidate(trim($elements[$element_name]), $rule_name, $paras, $is_required);
                if ($validate_msg) {
                    $result[$element_name] = vsprintf($validate_msg, $rule_msg);
                }
            }
        }
        return $result;
    }

    /**
     * 数据值验证
     *
     * @param mixed  $value
     * @param string $rule_name
     * @param array  $paras
     * @param bool   $is_required 是否必填
     * @return bool|mixed|null|string
     */
    protected static function valueValidate($value, $rule_name, $paras, $is_required = FALSE) {
        $rule_paras = $paras;
        if ($is_required && NULL === $value) {
            return FALSE;
        } elseif (!$is_required) {//可选参数且value为NULL
            return NULL;
        }
        if (is_numeric($rule_name)) {
            $rule_name = $paras;
            $paras = NULL;
            $rule_paras = [];
        }
        if (!isset(self::$_rules[$rule_name])) {
            return FALSE;
        }
        if (is_array($rule_paras)) {
            array_unshift($rule_paras, $value);
        } else {
            $rule_paras = [$value, $rule_paras];
        }
        $valid_result = NULL;
        $rule = self::$_rules[$rule_name];
        if (is_callable($rule)) {
            $valid_result = call_user_func_array($rule, $rule_paras);
        } else {
            if (is_string($rule)) {
                $valid_result = DataValidator::isMatched($value, $rule);
            }
        }
        if (!$valid_result) {
            $msg = isset(self::$_msg[$rule_name]) ? self::$_msg[$rule_name] : '';
            if (is_callable($msg)) {
                $msg = call_user_func_array($msg, $paras);
            }
            return $msg;
        }
        return NULL;
    }
}
