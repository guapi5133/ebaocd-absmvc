<?php
namespace eBaocd\AbsMvc;

use Apps\Rule\PurposeRule;
use eBaocd\Common\xFun;
use eBaocd\Common\xRedis;
use mysql_xdevapi\Collection;

class Model
{
    protected $clsname = '';
    protected $objarr = array();
    protected $_isdebug = FALSE;
    private $forcibly = TRUE; //强条件要求，where不能为空
    private $whereRaw = [];
    private $filedRaw = '*';
    private $orderRaw = '';
    private $limitRaw = [];
    private $columnRaw = ''; //列名
    private $columnKey = NULL;
    public $table = '';
    private $third_db = ''; //第三方数据库

    public function __construct()
    {
        $clsname       = get_called_class();
        $clsname       = str_replace("Model", "Rule", $clsname);
        $this->clsname = $clsname;
    }

    public function __call($name, $arguments)
    {
        $obj    = $this->GetClassObj();
        $method = new \ReflectionMethod($this->clsname, $name);

        return $method->invokeArgs($obj, $arguments);
    }

    /**
     * 获得需要使用的对象，为空则为本model对应的RULE.
     * 如：PItem_CheckModel，对应的rule则为PItem_ItemRule
     *
     * @param string $clsname RULE或别的MODEL类名
     *
     * @return mixed
     */
    public function GetClassObj($clsname = '')
    {
        if (is_object($clsname))
        {
            return $clsname;
        }

        if ($clsname == '')
        {
            $clsname = $this->clsname;
        }

        return new $clsname();

        if (!array_key_exists($clsname, $this->objarr) || !$this->objarr[$clsname])
        {
            $this->SetClassObj($clsname);
        }

        return $this->objarr[$clsname];
    }

    /**
     * 设置使用的类
     *
     * @param mixed $clsname 可以是类名，也可以是类实例
     */
    public function SetClassObj($clsname = '')
    {
        if (is_object($clsname))
        {
            $clsname2                = get_class($clsname);
            $this->objarr[$clsname2] = $clsname;
        }
        else
        {
            if ($clsname == '')
            {
                $clsname = $this->clsname;
            }
            $this->objarr[$clsname] = new $clsname();
        }
    }

    public function __destruct()
    {
        //$this->objarr = null;
    }

    /**
     * @param string $configname 配置文件中的数据库连接信息
     */
    public function SetDb($configname)
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($configname);
    }

    /**
     * 映射到rule层去格式化查询条件为字符串，以实现更多的组合
     *
     * @param mixed $where
     *
     * @return string
     */
    public function WhereExpr($where)
    {
        $cr     = $this->GetClassObj();
        $result = $cr->_WhereExpr($where);

        return $result;
    }

    public function WhereFormat($where)
    {
        $cr     = $this->GetClassObj();
        $result = $cr->WhereExpr($where);

        return $result;
    }

    /**
     * 取得单个查询
     *
     * @param mixed $where array("courseid"=>$id)
     */
    public function GetOne($where, $field = "*", $tbname = '')
    {
        $result = $this->GetList($where, '', -1, 1, $field, $tbname);

        return $result[0] ?? [];
    }

    /**
     * 根据id查询单条记录
     *
     * @param        $id
     * @param string $field
     * @param string $tbname
     *
     * @return array
     */
    public function GetById($id, $field = '*', $tbname = '')
    {
        if ($id < 1)
        {
            return FALSE;
        }

        return $this->GetOne(['id' => $id], $field, $tbname);
    }

    /**
     * 取得列表(一般查询)
     *
     * @param mixed  $where
     * @param string $orderby 不含order by
     * @param int    $page
     * @param int    $pagesize
     * @param string $field   字段筛选
     *
     * @return array("allrow"=>array(),"allnum"=>0);:
     */
    public function GetList($where = 'id > 0', $orderby = '', $page = 0, $pagesize = 0, $field = "*", $tbname = '', $tbpre = TRUE)
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($this->db_select()); //要连接的数据库

        if ($tbname != '') //完整表名
        {
            $srctb = $cr->GetTableName(FALSE);
            $cr->SetTable($tbname);
            $rtn = $cr->GetList($where, $field, $orderby, $pagesize, $page, $tbpre);
            $cr->SetTable($srctb);
        }
        else
        {
            $rtn = $cr->GetList($where, $field, $orderby, $pagesize, $page, $tbpre);
        }

        if ($this->_isdebug)
        {
            echo $cr->GetQueryString();
        }

        return $rtn;
    }

    /**
     * 获得单条数据
     *
     * @param string $field 字段名
     * @param string $where 不含WHERE的条件串，用于复杂条件
     */
    public function GetOneExt($field, $where, $tbname = '')
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($this->db_select()); //要连接的数据库

        if ($tbname != '')
        {
            $srctb = $cr->GetTableName(FALSE);
            $cr->SetTable($tbname);
            $rtn = $cr->GetOneExt($field, $where);
            $cr->SetTable($srctb);
        }
        else
        {
            $rtn = $cr->GetOneExt($field, $where);
        }

        if ($this->_isdebug)
        {
            echo $cr->GetQueryString();
        }

        return $rtn;

    }

    /**
     * 获得符合条件的记录总数
     *
     * @param mixed $where array(推荐)或 string
     * @param str   $tbname
     *
     * @return num
     */
    public function IsExists($where, $tbname = '', $tbcontainspre = FALSE)
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($this->db_select()); //要连接的数据库

        $rtn = 0;
        if ($tbname != '')
        {
            $srctb = $cr->GetTableName($tbcontainspre);
            $cr->SetTable($tbname);
            $rtn = $cr->IsExists($where);
            $cr->SetTable($srctb);
        }
        else
        {
            $rtn = $cr->IsExists($where);
        }


        if ($this->_isdebug)
        {
            echo $cr->GetQueryString();
        }

        return $rtn;
    }

    /**
     * 新增一条数据
     *
     * @param array  $data
     * @param string $tbname
     *
     * @return num
     */
    public function AddOne(array $data, $tbname = '', $rtnInsertId = TRUE)
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($this->db_select()); //要连接的数据库

        if ($tbname != '')
        {
            $srctb = $cr->GetTableName(FALSE);
            $cr->SetTable($tbname);
            $id = $cr->Insert($data, $rtnInsertId);
            $cr->SetTable($srctb);
        }
        else
        {
            $id = $cr->Insert($data, $rtnInsertId);
        }

        //写入操作数据库日志
        xFun::userActionLog($cr->GetQueryString(), 'db');

        if ($this->_isdebug)
        {
            echo $cr->GetQueryString();
        }

        return $id;
    }

    /**
     * 批量添加 参数必须是二维数组
     *
     * @param array  $para  要插入的内容 为空退出
     * @param string $table 表名  为空则是当前表
     *
     * @return string $sql  组合好的sql语句
     */
    public function AddAll($para, $table = '')
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($this->db_select()); //要连接的数据库

        $res = $cr->InsertArr($para, $table);
        $sql = $cr->GetQueryString();

        //写入操作数据库日志
        //xFun::userActionLog($sql, 'db');

        return $res;
    }

    /**
     * 更新数据
     *
     * @param array  $data   要更新的数据，key字段名，VAL是字段值
     * @param array  $where  筛选条件
     * @param string $tbname 目前被当作表名来用，在日后版本中可能会被替，因为增加了类的耦合度
     *
     * @return Ambiguous
     */
    public function UpdateOne(array $data, $where, $tbname = '')
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($this->db_select()); //要连接的数据库

        if ($tbname != '')
        {
            $srctb = $cr->GetTableName(FALSE);
            $cr->SetTable($tbname);
            $num = $cr->Update($data, $where);
            $cr->SetTable($srctb);
        }
        else
        {
            $num = $cr->Update($data, $where);
        }

        //写入操作数据库日志
        xFun::userActionLog($cr->GetQueryString(), 'db');

        if ($this->_isdebug)
        {
            echo $cr->GetQueryString();
        }

        return $num;
    }

    /**
     * 更新关联表数据
     *
     * @param array  $del_arr 要删除的数据，形如array("pk"=>"id","pv"=>array())
     * @param array  $add_arr 要添加的数据，开如array(array("id"=>"","cnname"=>""))
     * @param string $tbname  关联表名，为空则为当前RULE表名,不含前缀
     *
     * @return array array("delnum"=>0,"addnum"=>0);
     */
    public function UpdateAssoc(array $del_arr, array $add_arr, $tbname = "")
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($this->db_select()); //要连接的数据库

        $result = $cr->UpdateAssoc($del_arr, $add_arr, $tbname);

        //写入操作数据库日志
        xFun::userActionLog($cr->GetQueryString(), 'db');

        if ($this->_isdebug)
        {
            echo $cr->GetQueryString();
        }

        return $result;
    }

    /**
     * 删除单个
     *
     * @param array  $pk_arr
     * @param string $tbname 为空则为RULE默认表名，否则为设定值
     *
     * @return num 影响的行数
     */
    public function DeleteOne(array $pk_arr, $tbname = '')
    {
        $cr = $this->GetClassObj();
        $cr->SetDb($this->db_select()); //要连接的数据库

        if ($tbname != '')
        {
            $srctb = $cr->GetTableName(FALSE);
            $cr->SetTable($tbname);
            $num = $cr->Delete($pk_arr);
            $cr->SetTable($srctb);
        }
        else
        {
            $num = $cr->Delete($pk_arr);
        }

        //写入操作数据库日志
        xFun::userActionLog($cr->GetQueryString(), 'db');

        if ($this->_isdebug)
        {
            echo $cr->GetQueryString();
        }

        return $num;
    }

    /**更新插入（存在就修改，不存在就插入）
     *
     * @param        $data   要更新的数据，key字段名，VAL是字段值
     * @param array  $where  筛选条件
     * @param string $tbname 关联表名，为空则为当前RULE表名,不含前缀
     *
     * @return Ambiguous|num
     */
    public function RepOne($data, array $where, $tbname = '')
    {
        $num = $this->IsExists($where, $tbname);
        if ($num > 0)
        {
            $id = $this->UpdateOne($data, $where, $tbname);
        }
        else
        {
            $id = $this->AddOne($data, $tbname);
        }

        return $id;
    }


    /**
     * 返回键值对，如select id,cnname From table,
     * iskeyvalue=false则返回array("id的值"=>array("id的值","cnname的值"))
     * iskeyvalue=true则返回array("id的值"=>"cnname的值")
     *
     * @param array   $where
     * @param string  $field
     * @param boolean $iskeyvalue 是否返回键值对
     */
    public function FetchAssoc(array $where, $field, $iskeyvalue = FALSE, $tbname = '', $orderby = '')
    {
        $cr = $this->GetClassObj();

        //如果未开启事务，读写分离
        if (!xFun::getGlobalConfig('transaction'))
        {
            $cr->SetDb('readdb');
        }

        if ($tbname != '')
        {
            $srctb = $cr->GetTableName(FALSE);
            $cr->SetTable($tbname);
            //public function FetchAssoc($sql, $bind = array())
            $result = $cr->FetchAssoc($where, $field, $iskeyvalue, $orderby);
            $cr->SetTable($srctb);
        }
        else
        {
            $result = $cr->FetchAssoc($where, $field, $iskeyvalue, $orderby);
        }

        if ($this->_isdebug)
        {
            echo $cr->GetQueryString();
        }

        return $result;
    }

    /**
     * 获得当前查询的SQL
     */
    public function GetQueryString()
    {
        $cr = $this->GetClassObj();

        //如果未开启事务，读写分离
        if (!xFun::getGlobalConfig('transaction'))
        {
            $cr->SetDb('readdb');
        }

        return $cr->GetQueryString();
    }

    /**
     * 获取事务对象
     * @return object
     */
    public function GetTransaction()
    {
        $rule = new PurposeRule();
        xFun::setGlobalConfig('transaction', TRUE); //开启事务

        return $rule->GetDb();
    }

    //获取要连接的数据库
    public function db_select()
    {
        $db = 'db';
        if (!empty($this->third_db)) //如果指定了第三方数据库
        {
            $db = $this->third_db;
        }
        else //读取自己的数据库
        {
            //如果未开启事务，读写分离
            if (!xFun::getGlobalConfig('transaction'))
            {
                $db = 'readdb';
            }
        }

        return $db;
    }

    //-------------- 链式调用 --------------

    public function where($where = []) //为空则表示查询所有
    {
        $this->whereRaw = $where;

        return $this;
    }

    public function whereById($id)
    {
        return $this->where(['id' => $id]);
    }

    public function filed($filed = '*')
    {
        $this->filedRaw = $filed;

        return $this;
    }

    public function order($order = '')
    {
        $this->orderRaw = $order;

        return $this;
    }

    public function limit($page = -1, $pagesize = 0) //默认查所有
    {
        if ($page == 0)
        {
            $page = -1;
        }

        //从第一条开始查时，page要么是1，要么是负数，不能是0，因为框架里面会有一个 减1 的操作
        //如果写成了0，就是 -pagesize,pagesize 比如pagesize默认是30，就是 -30,30
        $this->limitRaw = [$page, $pagesize];

        return $this;
    }

    //返回指定列的数据，array_colomn()的效果
    public function column($column = '', $key = '')
    {
        $this->columnRaw = $column;
        $this->columnKey = $key;

        return $this;
    }

    //指定第三方数据库；如果为空则调用默认数据库
    public function thirdDb($db = '')
    {
        $this->third_db = $db;

        return $this;
    }

    public function setTable($table = '')
    {
        $this->table = $table;

        return $this;
    }

    //依次调用以下方法还原默认值
    public function setDefault()
    {
        foreach (['filed', 'order', 'limit', 'where', 'column', 'thirdDb'] as $value)
        {
            $this->$value();
        }

        return TRUE;
    }

    public function findOne()
    {
        $this->limit(-1, 1);

        return $this->findAll()[0] ?? [];
    }

    public function findAll()
    {
        if (!$this->validParaEmpty($this->table))
        {
            xFun::output(106);
        }

        list($page, $pagesize) = $this->limitRaw; //每次调用前会初始值，这里直接用就是

        $result = $this->GetList($this->whereRaw, $this->orderRaw, $page, $pagesize, $this->filedRaw, $this->table);
        $result = !empty($this->columnRaw) && !empty($result) ? array_column($result, $this->columnRaw, $this->columnKey) : $result;

        return $result;
    }

    public function findById(int $id, $filed = 'id')
    {
        if (!is_numeric($id))
        {
            exit('sql error');
        }

        $this->whereRaw = [$filed => $id];

        return $this->findOne();
    }

    public function insert($data)
    {
        if (!$this->validParaEmpty($this->table))
        {
            xFun::output(106);
        }

        if (!$this->validParaEmpty($data))
        {
            xFun::output(105);
        }

        return $this->AddOne($data, $this->table);
    }

    public function insertAll($data)
    {
        if (!$this->validParaEmpty($this->table))
        {
            xFun::output(106);
        }

        if (!$this->validParaEmpty($data))
        {
            xFun::output(105);
        }

        return $this->AddAll($data, $this->table);
    }

    public function update($data, $where = [])
    {
        if (!$this->validParaEmpty($this->table))
        {
            xFun::output(106);
        }

        if (!$this->validParaEmpty($data))
        {
            xFun::output(105);
        }

        //update一定要带where条件，不带的话默认是修改全表，这是非常危险的事情
        //如果一定要修改全表，执行一下 noForcibly 临时取消掉where验证
        if (empty($where) && empty($this->whereRaw) && $this->forcibly)
        {
            xFun::output(106);
        }

        if (!empty($where))
        {
            $this->where($where);
        }

        return $this->UpdateOne($data, $this->whereRaw, $this->table);
    }

    public function updateById($data, $id)
    {
        if (!is_numeric($id))
        {
            exit('sql error');
        }

        return $this->update($data, ['id' => $id]);
    }

    public function delete($where = [])
    {
        if (!$this->validParaEmpty($this->table))
        {
            xFun::output(106);
        }

        //一定要带where条件，不带的话默认是全表，这是非常危险的事情
        //如果一定要全表，执行一下 noForcibly 临时取消掉where验证
        if (empty($where) && empty($this->whereRaw) && $this->forcibly)
        {
            xFun::output(106);
        }

        if (!empty($where))
        {
            $this->where($where);
        }

        return $this->DeleteOne($this->whereRaw, $this->table);
    }

    public function count()
    {
        return $this->IsExists($this->whereRaw, $this->table);
    }

    public function querySql($sql)
    {
        //除了指定db不能走链式调用哈，一切操作在sql语句里面自己处理
        $rule = new DbRule();
        if (!empty($this->third_db))
        {
            $rule->SetDb($this->third_db);
        }

        return $rule->Query($sql);
    }

    //临时取消强制where
    public function noForcibly()
    {
        $this->forcibly = FALSE;

        return $this;
    }

    //验证参数是否为空
    public function validParaEmpty($para): bool
    {
        return empty($para) ? FALSE : TRUE;
    }

    //修改（追加）status条件为 status = ?，默认只查找status=1（有效）数据
    public function statusStrong_1($status = STATUS_ENABLE)
    {
        if (empty($status))
        {
            return $this;
        }

        if (isset($this->whereRaw['status']))
        {
            $this->whereRaw['status'] = $status;
        }
        else
        {
            $temp = $this->whereRaw;
            foreach ($temp as $key => $value)
            {
                if (stristr($key, 'status_') !== FALSE)
                {
                    unset($this->whereRaw[$key]);
                    $this->whereRaw['status'] = $status;
                    break;
                }
            }
        }

        return $this;
    }

    public function statusStrong($status = STATUS_ENABLE)
    {
        if (empty($status))
        {
            return $this;
        }

        $temp = $this->whereRaw;
        foreach ($temp as $key => $value)
        {
            if (stristr($key, 'status_') !== FALSE)
            {
                unset($this->whereRaw[$key]);
                break;
            }
        }
        $this->whereRaw['status'] = $status;

        return $this;
    }

    //查询主键id数据并验证是否有效，并判断是否返回数据
    public function ifEnableById($id, $return = TRUE)
    {
        $info = $this->findById($id);

        if (empty($info) || $info['status'] >= STATUS_DISABLE)
        {
            return FALSE;
        }

        return $return ? $info : TRUE;
    }

    //-------------- 链式调用 -----------------
}

?>
