<?php
/*
 *    Copyright 2012-2016 Youzan, Inc.
 *
 *    Licensed under the Apache License, Version 2.0 (the "License");
 *    you may not use this file except in compliance with the License.
 *    You may obtain a copy of the License at
 *
 *        http://www.apache.org/licenses/LICENSE-2.0
 *
 *    Unless required by applicable law or agreed to in writing, software
 *    distributed under the License is distributed on an "AS IS" BASIS,
 *    WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *    See the License for the specific language governing permissions and
 *    limitations under the License.
 */
namespace rust\dbo;

use rust\exception\storage\SqlCanNotFindTableNameException;
use rust\exception\storage\SqlTypeException;
use rust\interfaces\ISqlResultType;

class SqlParser {
    private $sqlMap;

    public function setSqlMap($sqlMap) {
        $this->sqlMap=$sqlMap;
        return $this;
    }

    public function parse($key) {
        $map=$this->sqlMap;
        if ($key == 'table') {
            return $this;
        }
        $expKey=explode('_', $key);
        if (!isset($expKey[0])) {
            unset($this->sqlMap[$map]);
            return $this;
        }
        $map['sql']=trim($map['sql']);
        $map['require']=isset($map['require']) ?? [];
        $map['limit']=isset($map['limit']) ?? [];
        $map['rw']='w';
        if (preg_match('/^\s*(?:select|show)/i', $map['sql'])) {
            $map['rw']='r';
        }
        $map['sql_type']=$this->getSqlType($map['sql']);
        $map['result_type']=$this->checkResultType($map['sqlType']??$map['sql_type']);
        $this->parseTable($map);
        $this->sqlMap=$map;
        return $this;
    }

    public function getSqlMap() {
        return $this->sqlMap;
    }

    private function getSqlType($sql) {
        preg_match('/^\s*((?:INSERT|SELECT|UPDATE|DELETE|CREATE TABLE|TRUNCATE){1})/is', $sql, $match);
        if (!$match) {
            throw new SqlTypeException('sql语句类型错误,必须是INSERT|SELECT|UPDATE|DELETE|CREATE TABLE|TRUNCATE其中之一');
        }
        return strtolower(str_replace(' ', '_', trim($match[0])));
    }

    /**
     * @param string $mapKey
     *
     * @return int
     */
    private function checkResultType($mapKey) {
        switch ($mapKey) {
        case 'insert' :
            $resultType=ISqlResultType::LAST_INSERT_ID;
            break;
        case 'insertNoId' :
            $resultType=ISqlResultType::AFFECTED_ROWS;
            break;
        case 'update' :
            $resultType=ISqlResultType::UPDATE;
            break;
        case 'truncate':
        case 'delete' :
            $resultType=ISqlResultType::DELETE;
            break;
        case 'row' :
            $resultType=ISqlResultType::ROW;
            break;
        case 'select' :
            $resultType=ISqlResultType::SELECT;
            break;
        case 'batch' :
            $resultType=ISqlResultType::BATCH;
            break;
        case 'count' :
            $resultType=ISqlResultType::COUNT;
            break;
        default :
            $resultType=ISqlResultType::RAW;
            break;
        }
        return $resultType;
    }

    /**
     * @param $map
     *
     * @throws SqlCanNotFindTableNameException
     */
    private function parseTable(&$map) {
        //正则匹配数据表名，表名中不能有空格
        $tablePregMap=[
            'INSERT' =>'/(?<=\sINTO\s)\S*/i',
            'SELECT' =>'/(?<=\sFROM\s)\S*/i',
            'DELETE' =>'/(?<=\sFROM\s)\S*/i',
            'UPDATE' =>'/(?<=UPDATE\s)\S*/i',
            'REPLACE'=>'/(?<=REPLACE\s)\S*/i',
            'CREATE' =>'/(?<=TABLE\s)(?:IF NOT EXISTS\s+)?\S*/i',
            'TRUNCATE' =>'/^TRUNCATE\s+(\S+)/i',
        ];
        if (isset($map['table']) && '' !== $map['table']) {
            return;
        }
        $sql=$map['sql'];
        $pos=strpos($sql, ' ');
        $type=strtoupper(substr($sql, 0, $pos));
        $matches=null;
        if (!isset($tablePregMap[$type])) {
            throw new SqlCanNotFindTableNameException('Can not find table name, please check your sql type.');
        }
        preg_match($tablePregMap[$type], $sql, $matches);
        if (!is_array($matches) || !isset($matches[0])) {
            throw new SqlCanNotFindTableNameException('Can not find table name, please check your sql type!');
        }
        $tableMatch=$matches[0];
        $tableNames=explode(' ', $tableMatch);
        $table=sizeof($tableNames) > 0 ? array_pop($tableNames) : $tableMatch;
        //去除`符合和库名
        if (false !== ($pos=strrpos($table, '.'))) {
            $table=substr($table, $pos + 1);
        }
        $table=trim($table, '`');
        if ('' == $table || !strlen($table)) {
            throw new SqlCanNotFindTableNameException('Can\'t get table name');
        }
        //TODO:remove分表
        if (isset($map['sharding']) && $map['sharding']) {
            $pattern=$this->getTablePattern($type, $tableMatch, str_replace($table, $map['sharding'], $tableMatch));
            $map['sql']=$pattern ? preg_replace($pattern['pattern'], $pattern['replace'], $map['sql']) : $map['sql'];
        }
        $map['table']=$table;
    }

    /**
     * @param string $type
     * @param string $table
     * @param string $sharding
     *
     * @return array
     */
    private function getTablePattern(string $type, string $table, string $sharding) : array {
        $tableShardingMap=[
            'INSERT' =>[
                'pattern'=>'/\sINTO\s%s/i',
                'replace'=>' INTO %s'
            ],
            'SELECT' =>[
                'pattern'=>'/\sFROM\s%s/i',
                'replace'=>' FROM %s'
            ],
            'DELETE' =>[
                'pattern'=>'/\sFROM\s%s/i',
                'replace'=>' FROM %s',
            ],
            'UPDATE' =>[
                'pattern'=>'/UPDATE\s%s/i',
                'replace'=>'UPDATE %s',
            ],
            'REPLACE'=>[
                'pattern'=>'/REPLACE\s%s/i',
                'replace'=>'REPLACE %s'
            ],
            'CREATE' =>[
                'pattern'=>'/TABLE\s%s/i',
                'replace'=>'TABLE %s'
            ],
            'TRUNCATE' =>[
                'pattern'=>'/TRUNCATE\s%s/i',
                'replace'=>'TRUNCATE %s'
            ]
        ];
        $shardingMap=$tableShardingMap[$type]??[];
        if (!$shardingMap) {
            return [];
        }
        return [
            'pattern'=>sprintf($shardingMap['pattern'], $table),
            'replace'=>sprintf($shardingMap['replace'], $sharding)
        ];
    }
}