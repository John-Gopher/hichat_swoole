<?php
/**
 * 直接面向用户的基础模型类
 */
namespace Framework\Model;
use \Framework\Base\Object;
use \Framework\Model\Filter;
class BaseModel extends Object
{
    var $db = null;
    /* 所映射的数据库表 */
    var $table = '';

    /* 主键 */
    var $prikey= '';

    /* 别名 */
    var $alias = '';

    /* 模型的名称 */
    var $_name   = '';

    /* 表前缀 */
    var $_prefix = '';

    /* 数据验证规则 */
    var $_autov = array();

    /* 临时保存已删除的数据 */
    var $_dropped_data = array();

    /* 关系(定义关系时，只有belongs_to以及MANY_TO_MANY需要指定reverse反向关系) */
    var $_relation = array();
    //过滤器
    var $filter = '';
    //字段列表（带注释）
    var $fields = array();
    var $data = array();
    function __construct($params='', $db='')
    {
        if(!$db){
            $db = db();
        }
        $this->BaseModel($params, $db);
    }
   
    /**
     *  构造函数
     *
     *  @author LorenLei
     *  @param  array  $params
     *  @param  object $db
     *  @return void
     */
    function BaseModel($params='', $db)
    {
        if(!$db){
            $db = db();
        }
        $this->db = $db;
        !$this->alias && $this->alias = $this->table;
        $this->_prefix = DB_PREFIX;
        $this->table = $this->_prefix . $this->table;
        if (!empty($params))
        {
            foreach ($params as $key => $value)
            {
                $this->$key = $value;
            }
        }
        $this->filter = new Filter();
    }
    /**
     * 根据表字段名匹配字段中文解释
     */
	function match_field($field)
	{
        if(empty($this->fields)){
            $this->fields = $this->db->table_fields($this->table);
        }
        if(!$this->fields){
            $this->_error('获取表'.$this->table.'的字段集失败');
        }
        return !empty($this->fields[$field]) ? $this->fields[$field]:$field;
		
	}
    /**
     *    获取模型名称
     *
     *    @author    LorenLei
     *    @return    void
     */
    function getName()
    {
        return $this->_name;
    }

    /**
     *    获取单一一条记录
     *
     *    @author    LorenLei
     *    @param     mixed $params
     *    @return    array
     */
    function getOne($params)
    {
        $data = $this->find($params);
        if (!is_array($data))
        {
            return array();
        }

        return current($data);
    }



    /**
     *  根据一定条件找出相关数据(不连接其它模型，直接通过JOIN语句来查询)
     *
     *  @author LorenLei
     *  @param  mixed  $params
     *  @return array
     */
    function find($params = array())
    {
        // 一个用户有多个订单
      /*  'has_order' => array(
        'model'         => 'order',
        'type'          => HAS_MANY,
        'foreign_key'   => 'buyer_id',
        'dependent' => true
    ),
        'belongs_to_user'  => array(
        'type'          => BELONGS_TO,
        'reverse'       => 'has_order',
        'model'         => 'member',
    ),*/
        //array('fields'=>'','join'=>'has_store,belongs_to_user','index_key','order','limit'=>'0,100','count'=>true);
        extract($this->_initFindParams($params));

        /* 字段(SELECT FROM) */
        $fields = $this->getRealFields($fields);
        $fields == '' && $fields = '*';

        $tables = $this->table . ' ' . $this->alias;
        
        /* 左联结(LEFT JOIN) */
        $join_result = $this->_joinModel($tables, $join);
      
        /* 原来为($join_result || $index_key)，忘了最初的用意，默认加上主键应该是只为了为获得索引的数组服务的，因此只跟索引键是否是主键有关 */
        if ($index_key == $this->prikey || (is_array($index_key) && in_array($this->prikey, $index_key)))
        {
            /* 如果索引键里有主键，则默认在要查询字段后加上主键 */
            $fields .= ",{$this->alias}.{$this->prikey}";
        }
       
        /* 条件(WHERE) */
        $conditions = $this->_getConditions($conditions, true);
        /* 排序(ORDER BY) */
        $order && $order = ' ORDER BY ' . $this->getRealFields($order);
        /* 分页(LIMIT) */
        $limit && $limit = ' LIMIT ' . $limit;
        if ($count)
        {
              return $this->db->getOne("SELECT COUNT(*) as c FROM {$tables}{$conditions}");
        }

        /* 完整的SQL */
        $sql = "SELECT {$fields} FROM {$tables}{$conditions}{$order}{$limit}";

        $index_key = $index_key ? $this->db->getAllWithIndex($sql, $index_key) :
                            $this->db->getAll($sql);
        return $index_key;
    }

    /**
     *  关联查找多对多关系的记录
     *
     *  @author LorenLei
     *  @param  mixed  $params
     *  @return array
     */
    function findAll($params = array())
    {
        $params = $this->_initFindParams($params);
        extract($params);
        $pri_data = $this->find($params);   
              
        //先找出通过JOIN获得的数据集
        if (!empty($include) && !empty($pri_data))
        {
            $ids = array();
            if ($index_key != $this->prikey)
            {
                foreach ($pri_data as $pk => $pd)
                {
                    $ids[] = $pd[$this->prikey];
                }
            }
            else
            {
                $ids = array_keys($pri_data);
            }
            
            foreach ($include as $relation_name => $find_param)
            {
                if (is_string($find_param))
                {
                    $relation_name = $find_param;
                    $find_param= array();
                }

                      
                    	
                /* 依次获取关联数据，并将其放放主数据集中 */
                $related_data = $this->getRelatedData($relation_name, $ids, $find_param);

                is_array($related_data) && $pri_data = $this->assemble($relation_name, $related_data, $pri_data);
            }
        }

        return $pri_data;
    }


    /**
     * 数据校验
     * @param array $data：校验数据,boolen $enough：是否校验数据充足,也就是把 $this->_autov里的字段全部校验一遍
     * @return bool
     */
    function check($data = array(),$checkEnough=true){
        $data =  $this->data = $data ? $data: $this->data;
        if (empty($data) || ($checkEnough &&!$this->dataEnough($data)))
        {
            $this->_error('数据不充足');
            return false;
        }
        $autov = $this->_autov;
        //选择性根据模型_autov字段定义的校验数据集合做数据校验
        if(!$checkEnough){
            $autov_ = array();
            foreach($data as $field => $val){
                isset($data[$field]) && $autov_[$field] = $autov[$field];
            }
        }else{
            $autov_ = $autov;
        }

        $data = $this->filter->_valid($autov_,$data,$this);

        if (!$data)
        {
            $this->_error('无效数据');
            return false;
        }
    }
    /**
     *  添加一条记录
     *
     *  @author LorenLei
     *  @param  array $data
     *  @return mixed
     */
    function save($data=array(), $replace = false)
    {
        $data =  $this->data = $data ? $data: $this->data;
        if (empty($data) || !$this->dataEnough($data))
        {
            return false;
        }
        //temp
        $data = $this->filter->_valid($this->_autov,$data,$this);
        
        if (!$data)
        {
            $this->_error('no_valid_data');
            return false;
        }
        $insert_info = $this->_getInsertInfo($data);
        $mode = $replace ? 'REPLACE' : 'INSERT';
        $this->db->query("{$mode} INTO {$this->table}{$insert_info['fields']} VALUES{$insert_info['values']}");
        $insert_id = $this->db->insert_id();
        if ($insert_id)
        {
            if ($insert_info['length'] > 1)
            {
                for ($i = $insert_id; $i < $insert_id + $insert_info['length']; $i++)
                {
                    $id[] = $i;
                }
            }
            else
            {
                /* 添加单条记录 */
                $id = $insert_id;
            }
        }

        return $id;
    }
    /**
     *  简化更新操作
     *
     *  @author LorenLei
     *  @param  array   $edit_data
     *  @param  mixed   $conditions
     *  @return bool
     */
    function edit($conditions, $edit_data)
    {
        $edit_data =  $this->data = $edit_data ? $edit_data: $this->data;
        if (empty($edit_data))
        {
            return false;
        }
        $edit_data = $this->filter->_valid($this->_autov,$edit_data,$this);
       
        if (!$edit_data)
        {
            return false;
        }
        
        //exit;
        $edit_fields = $this->_getSetFields($edit_data);
        $conditions  = $this->_getConditions($conditions, false);
        $this->db->query("UPDATE {$this->table} SET {$edit_fields}{$conditions}");

        return $this->db->affected_rows();

    }

    /**
     * @param $conditions 条件，格式如：
     * @param string $fields 级联删除需要的字段
     * @param string $cascade 是否级联删除
     * @return int|void
     */
    function delete($conditions, $useStatus = false,$fields = '', $cascade='false')
    {
        if (empty($conditions))
        {
            return;
        }
        if ($conditions === DROP_CONDITION_TRUNCATE)
        {
            $conditions = '';
        }
        else
        {
            $conditions = $this->_getConditions($conditions, false);
        }

        if($cascade)
        {
            /* 保存删除的记录的主键值，便于关联删除时使用 */
            $fields && $fields = ',' . $fields;

            /* 这是个瓶颈，当删除的数据量非常大时会有问题 */
            $this->_saveDroppedData("SELECT {$this->prikey}{$fields} FROM {$this->table}{$conditions}");

            $droped_data = $this->getDroppedData();
            if (empty($droped_data))
            {
                return 0;
            }

        }
        if($useStatus){
            $this->db->query("update  {$this->table} set iStatus=1 {$conditions}");
        }else{
            $this->db->query("DELETE FROM {$this->table}{$conditions}");
        }

        $affectedRows = $this->db->affected_rows();
        if ($affectedRows > 0 && $cascade)
        {
            /* 删除依赖数据 */
            $this->dropDependentData(array_keys($droped_data));
        }

        return $affectedRows;
    }
    /**
     *  获取条件句段
     *
     *  @author LorenLei
     *  @param  mixed   $conditions
     *  @return string
     */
    function _getConditions($conditions, $if_add_alias = false)
    {
        $alias = '';
        if ($if_add_alias)
        {
            $alias = $this->alias . '.';
        }
        if (is_numeric($conditions))
        {
            /* 如果是一个数字或数字字符串，则认为其是主键值 */
            return " WHERE {$alias}{$this->prikey} = {$conditions}";
        }
        elseif (is_string($conditions))
        {
            /* 如果是字符串，则认为其是SQL自定义条件 */
            if (substr($conditions, 0, 6) == 'index:')
            {
                $value  =   substr($conditions, 6);
                return $value ? " WHERE {$alias}{$this->prikey}='{$value}'" : '';
            }
            else
            {
                return $conditions ? ' WHERE ' . $conditions : '';
            }
        }
        elseif (is_array($conditions))
        {
            /* 如果是数组，则认为其是一个主键集合 */
            if (empty($conditions))
            {
                return '';
            }
            foreach ($conditions as $_k => $_v)
            {
                if (!$_v)
                {
                    unset($conditions[$_k]);
                }
            }
            $conditions = array_unique($conditions);

            return ' WHERE ' . $alias .$this->prikey . ' ' . db_create_in($conditions);
        }
        elseif (is_null($conditions))
        {
            return '';
        }
    }
    /**
     *  初始化查询参数
     *
     *  @author LorenLei
     *  @param  array $params
     *  @return array
     */
    function _initFindParams($params)
    {
        $arr = array(
            'include'  => array(),
            'join'=> '',
            'conditions' => '',
            'order'      => '',
            'fields'     => '',
            'limit'      => '',
            'count'      => false,
            'index_key'  => $this->prikey,
        );
        if (is_array($params))
        {
            return array_merge($arr, $params);
        }
        else
        {
            $arr['conditions'] = $params;
            return $arr;
        }
    }

    /**
     *  按指定的方式LEFT JOIN指定关系的表
     *
     *  @author LorenLei
     *  @param  string $table
     *  @param  string $join_object
     *  @return string
     */
    function _joinModel(&$table, $join)
    {
        $result = false;
        if (empty($join))
        {
            return false;
        }

        /* 获取要关联的关系名 */
        $relation = preg_split('/,\s*/', $join);
        array_walk($relation, create_function('&$item, $key', '$item=trim($item);'));
        foreach ($relation as $_r)
        {
            /* 获取关系信息 即模型类里定义的关系数组 */
            if (!($_mi = $this->getRelation($_r)))
            {
                /* 没有该关系则跳过 */
                continue;
            }
       
    	
            $join_string = $this->_getJoinString($_mi);
            /* 关联关系为$_mi的模型 */          
             //$join_string 输出string(72) " LEFT JOIN ys_order_goods order_goods ON payback.id=order_goods.order_id"  
            if ($join_string)
            {
                /* 连接 */
                $table .= $join_string;
                $result = true;
            }
            
        }

        return $result;
    }
    
    /*  重点理解，理解关系模型核心 */
    function _getJoinString($relation_info)
    {
        switch ($relation_info['type'])
        {
            case HAS_ONE:
                $model =& m($relation_info['model']);

                /* 联合限制 */
                $ext_limit = '';
                $relation_info['ext_limit'] && $ext_limit = ' AND ' . $this->_getExtLimit($relation_info['ext_limit'], $model->alias);//须加上当前被关联表的别名，因为有可能存在多个JOIN，并且可能存在同名字段。

                /* 获取参考键，默认是本表主键(直接拥有)，否则为间接拥有 */
                $refer_key = isset($relation_info['refer_key']) ? $relation_info['refer_key'] : $this->prikey;

                /* 本表参考键=外表外键 */
                return " LEFT JOIN {$model->table} {$model->alias} ON {$this->alias}.{$refer_key}={$model->alias}.{$relation_info['foreign_key']}{$ext_limit}";
            break;
            case BELONGS_TO:
                /* 属于关系与拥有是一个反向的关系 */
                $model =& m($relation_info['model']);
                $be_related = $model->getRelation($relation_info['reverse']);
                if (empty($be_related))
                {
                    /* 没有找到反向关系 */
                    $this->_error('no_reverse_be_found', $relation_info['model']);

                    return '';
                }
                $ext_limit = '';
                !empty($relation_info['ext_limit']) && $ext_limit = ' AND ' . $this->_getExtLimit($relation_info['ext_limit'], $this->alias);
                /* 获取参考键，默认是外表主键 */
                $refer_key = isset($be_related['refer_key']) ? $be_related['refer_key'] :$model->prikey ;

                /* 本表外键=外表参考键 */                                             //外表的外键是本表的参考键         //外表的参考键
                return " LEFT JOIN {$model->table} {$model->alias} ON {$this->alias}.{$be_related['foreign_key']} = {$model->alias}.{$refer_key}{$ext_limit}";
            break;
            case MANY_TO_MANY:
                /* 连接中间表，本表主键=中间表外键 */
                $malias = isset($relation_info['alias']) ? $relation_info['alias'] : $relation_info['middle_table'];
                $ext_limit = '';
                $relation_info['ext_limit'] && $ext_limit = ' AND ' . $this->_getExtLimit($relation_info['ext_limit'], $malias);
                return " LEFT JOIN {$this->_prefix}{$relation_info['middle_table']} {$malias} ON {$this->alias}.{$this->prikey} = {$malias}.{$relation_info['foreign_key']}{$ext_limit}";
            break;
        }
    }

    /**
     *  组合数据
     *
     *  @author LorenLei
     *  @param  string  $relation_name  关系名称
     *  @param  array   $assoc_data     关联的数据
     *  @param  array   $pri_data       主表数据
     *  @return array
     */
    function assemble($relation_name, $assoc_data, $pri_data)
    {
        if (empty($pri_data) || empty($assoc_data))
        {
            $this->_error('assemble_data_empty');

            return $pri_data;
        }

        /* 获取关系信息 */
        $relation_info = $this->getRelation($relation_name);
        $model =& m($relation_info['model']);

        /* 循环主数据集 */
        foreach ($pri_data as $pk => $pd)
        {
            /* 循环从数据集 */
            foreach ($assoc_data as $ak => $ad)
            {
                /* 当主表的主键值与外表的的外键值相等时，将该外表的数据加入到主表数据中键为$model->alias的数组中 */
                if ($pd[$this->prikey] == $ad[$relation_info['foreign_key']])
                {
                    $pd[$model->alias][$ak] = $ad;
                    unset($assoc_data[$ak]);    //减少循环次数
                }
            }
            $pri_data[$pk] = $pd;
        }

        return $pri_data;
    }

    /**
     *    检查数据是否足够
     *
     *    @author    LorenLei
     *    @param     array $data
     *    @return    bool[true:足够,false:不足]
     */
    function dataEnough($data)
    {
        $required_fields = $this->getRequiredFields();
        if (empty($required_fields))
        {
            return true;
        }
        $is_multi = (key($data) === 0 && is_array($data[0]));
        foreach ($required_fields as $field)
        {
        	$field_name = $this->match_field($field);
            if ($is_multi)
            {
                foreach ($data as $key => $value)
                {
                    if (!isset($value[$field]))
                    {
                    	               	      
                        $this->_error($field_name.'不能为空!', $field_name);
                        return false;
                    }
                }
            }
            else
            {
                if (!isset($data[$field]))
                {
                	
                    $this->_error($field_name.'不能为空!', $field_name);

                    return false;
                }
            }
        }

        return true;
    }

    /**
     *    获取必须的字段列表
     *
     *    @author    LorenLei
     *    @return    array
     */
    function getRequiredFields()
    {
        $fields = array();
        if (is_array($this->_autov))
        {
            foreach ($this->_autov as $key => $value)
            {
                if (isset($value['required']) && $value['required'])
                {
                    $fields[] = $key;
                }
            }
        }

        return $fields;
    }

    function start() {
    	$this->db->start_transaction();
    }
    function commit() {
    	$this->db->commit();
    }
    function rollback(){
    	$this->db->rollback();
    }
}


?>
