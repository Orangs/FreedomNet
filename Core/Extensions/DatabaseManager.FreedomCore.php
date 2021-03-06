<?php
namespace Core\Extensions;

use Core\Libraries\FreedomCore\System\Database;
use Core\Libraries\FreedomCore\System\Text;
use \Exception as Exception;

// TODO: Create Custom Class For DBManager Exceptions Handling

class DatabaseManager {

    /**
     * Name of the table to be used
     * @var
     */
    protected $TableName;

    /**
     * Columns used for creation of the table
     * @var array
     */
    protected $Columns = [];

    /**
     * Conditions Array
     * @var array
     */
    protected $Where = [];

    /**
     * Relation Between Prepared Statement Value and Real Value
     * @var array
     */
    protected $Relations = [];

    /**
     * Value will be set to true, if Auto Increment already exists
     * @var bool
     */
    protected $isAIExists = false;

    /**
     * Name of the Auto Increment Column
     * @var string
     */
    protected $AIColumn = '';

    /**
     * Final SQL Query
     * @var string
     */
    protected $FinalQuery = '';

    /**
     * DatabaseManager constructor.
     */
    public function __construct(){
        $this->TableName = null;
        $this->Columns = [];
        $this->isAIExists = false;
        $this->AIColumn = null;
        $this->FinalQuery = null;
    }

    /**
     * Set Table Name
     * @param $TableName
     * @return $this
     */
    public function setTableName($TableName){
        $this->TableName = $TableName;
        return $this;
    }

    /**
     * Get Table Name
     * @return null
     */
    public function getTableName(){
        return $this->TableName;
    }

    /**
     * Add Column To The New Table
     * @param $ColumnName
     * @param $ColumnType
     * @param null $ColumnLength
     * @param bool|true $isNull
     * @param bool|false $isAI
     * @param bool|false $Default
     * @return $this
     */
    public function addColumn($ColumnName, $ColumnType, $ColumnLength = null, $isNull = true, $isAI = false, $Default = false){
        $ColumnName = strtolower($ColumnName);
        $ColumnType = strtolower($ColumnType);

        $this->verifyColumnName($ColumnName);
        $this->verifyColumnType($ColumnType);
        $this->isLengthApplicable($ColumnType, $ColumnLength);
        $this->setAIColumn($ColumnName, $isAI);

        $this->Columns[] = '`'.$ColumnName.'` '.
            $ColumnType.
            $this->setColumnLength($ColumnLength).
            $this->setNullableStatus($isNull).
            $this->setDefaultValue($ColumnType, $Default).
            $this->setAIStatus($ColumnName);
        return $this;
    }

    /**
     * Generate SQL Based on provided table data
     * @return string
     */
    public function build(){
        $TopString = 'CREATE TABLE IF NOT EXISTS `'.$this->TableName.'` ('.PHP_EOL;
        $MiddleString = '';
        for($i = 0; $i < count($this->Columns); $i++)
            if($i != count($this->Columns) -1)
                $MiddleString .= $this->Columns[$i].','.PHP_EOL;
            else
                if($this->isAIExists)
                    $MiddleString .= $this->Columns[$i].','.PHP_EOL;
                else
                    $MiddleString .= $this->Columns[$i].PHP_EOL;
        $PKString = 'PRIMARY KEY (`'.$this->AIColumn.'`)'.PHP_EOL;
        $LastString = ') ENGINE=InnoDB DEFAULT CHARSET=utf8;'.PHP_EOL;
        $FinalString = $TopString.$MiddleString.$PKString.$LastString;
        $this->FinalQuery = $FinalString;
        return $this;
    }

    /**
     * Remove New Lines From Query
     * @return mixed
     */
    public function stringify(){
        return str_replace(PHP_EOL, '', $this->FinalQuery);
    }

    /**
     * Return Pretty SQL Query
     * @return string
     */
    public function prettify(){
        return $this->FinalQuery;
    }

    /**
     * Execute Query
     * @param $Connection
     * @param bool|false $Return
     * @param bool|false $Multiple
     * @return mixed
     */
    public function executePDO($Connection, $Return = false, $Multiple = false){
        $Statement = $Connection->prepare($this->FinalQuery);
        foreach($this->Where as $Condition=>$Item)
            $Statement->bindParam($Condition, $this->Relations[$Condition]);
        $Statement->execute();
        if($Return)
            if($Multiple)
                if(Database::isEmpty($Statement))
                    return false;
                else
                    return $Statement->fetchAll(\PDO::FETCH_ASSOC);
            else
                if(Database::isEmpty($Statement))
                    return false;
                else
                    return $Statement->fetch(\PDO::FETCH_ASSOC);
        else
            return true;
    }

    /**
     * Add Column For Further Selection
     * @param $ColumnName
     * @return $this
     */
    public function selectColumn($ColumnName){
        $this->Columns[] = $ColumnName;
        return $this;
    }

    /**
     * Define Multiple Columns For Selection
     * @param $Columns
     * @return $this
     * @throws Exception
     */
    public function selectColumns($Columns){
        try {
            if(!is_array($Columns)){
                if(strstr($Columns, ','))
                    $Columns = explode(',', str_replace(' ', '', $Columns));
                else
                    throw new Exception('Given Variable is not an array, nor the colon separated string');
            }

            foreach($Columns as $Column)
                if(!in_array($Column, $this->Columns))
                    $this->Columns[] = $Column;
            return $this;
        } catch (Exception $e){
            $Message = '<h1>Database Manger Exception</h1><strong>Fatal Error: </strong>'.$e->getMessage();
            die($Message);
        }
    }

    /**
     * Add Conditions For Query
     * @param $Where
     * @param $Operator
     * @param $Value
     * @return $this
     */
    public function addCondition($Where, $Operator, $Value){
        if(!array_key_exists($Where, $this->Where))
            $this->Where[$Where] = ['operator' => $Operator, 'value' => $Value];
        return $this;
    }

    /**
     * Add Relation Between Where Key And PDO Prepared Value
     * @param $Where
     * @param $Value
     * @return $this
     */
    public function addRelation($Where, $Value){
        if(!array_key_exists($Where, $this->Relations))
            $this->Relations[$Where] = $Value;
        return $this;
    }

    /**
     * Create Select Statement
     * @return $this
     */
    public function select(){
        $Statement = 'SELECT ';
        for($i = 0; $i < count($this->Columns); $i++){
            if($i == (count($this->Columns) - 1))
                $Statement .= $this->Columns[$i].' ';
            else
                $Statement .= $this->Columns[$i].', ';
        }
        $Statement .= 'FROM '.$this->TableName.' ';
        if(!empty($this->Where)){
            $Statement .= 'WHERE ';
            $Count = count($this->Where);
            $Index = 1;
            foreach($this->Where as $Condition=>$Item){
                if($Index != $Count)
                    $Statement .= $Condition.' '.$Item['operator'].' '.$Item['value'].' AND ';
                else
                    $Statement .= $Condition.' '.$Item['operator'].' '.$Item['value'];

                $Index++;
            }
        }
        $this->FinalQuery = $Statement.';';
        return $this;
    }

    /**
     * Check if column name already exists
     * @param $ColumnName
     * @return bool
     * @throws Exception
     */
    private function verifyColumnName($ColumnName){
        try {
            if(in_array($ColumnName, $this->Columns))
                throw new Exception('Column with this name already exists!');
            else
                return true;
        } catch (Exception $e){
            die('<strong>'.$e->getMessage().'</strong>');
        }
    }

    /**
     * Check if given column allowed to be used by its type
     * @param $ColumnType
     * @return bool
     */
    private function verifyColumnType($ColumnType){
        $AllowedTypes = [
            'int',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint',
            'float',
            'double',
            'decimal',
            'date',
            'datetime',
            'timestamp',
            'char',
            'varchar',
            'blob',
            'text'
        ];
        try {
            if(in_array($ColumnType, $AllowedTypes))
                return true;
            throw new Exception('Column of this type is not allowed!');
        } catch (Exception $e){
            die('<strong>'.$e->getMessage().'</strong>');
        }
    }

    /**
     * Check if user can set length for given column type
     * @param $ColumnType
     * @return bool
     */
    private function isLengthApplicable($ColumnType, $LengthVar){
        $LengthAllowed = [
            'int' => true,
            'tinyint' => true,
            'smallint' => true,
            'mediumint' => true,
            'bigint' => true,
            'float' => true,
            'double' => true,
            'decimal' => true,
            'date' => false,
            'datetime' => false,
            'timestamp' => false,
            'char' => true,
            'varchar' => true,
            'blob' => false,
            'text' => false
        ];
        try {
            $isAllowed = $LengthAllowed[$ColumnType];
            if($LengthVar != null)
                if($isAllowed)
                    return true;
                else
                    throw new Exception('You cannot set length for this type of column!');
            else
                return true;
        } catch (Exception $e){
            die('<strong>'.$e->getMessage().'</strong>');
        }
    }

    /**
     * Check if user can set AI Column
     * @return bool
     */
    private function isAIAllowed(){
        try {
            if($this->isAIExists)
                throw new Exception('Auto Increment Column for table <i>'.$this->TableName.'</i> is Already Set!<br />It\'s name is: '.$this->AIColumn);
            else
                return true;
        } catch (Exception $e) {
            die('<strong>'.$e->getMessage().'</strong>');
        }
    }

    /**
     * Set new AI Column
     * @param $ColumnName
     * @param $SetAI
     * @return bool
     */
    private function setAIColumn($ColumnName, $SetAI){
        if(!$SetAI)
            return false;
        $this->isAIAllowed();
        $this->isAIExists = true;
        $this->AIColumn = $ColumnName;
    }

    /**
     * Set Length of the given column
     * @param $ColumnLength
     * @return string
     */
    private function setColumnLength($ColumnLength){
        if($ColumnLength != null)
            $ColumnLength = '('.$ColumnLength.') ';
        else
            $ColumnLength = ' ';
        return $ColumnLength;
    }

    /**
     * Set Null Status for given column
     * @param $isNull
     * @return string
     */
    private function setNullableStatus($isNull){
        if(!$isNull)
            $Nullable = 'NOT NULL ';
        else
            $Nullable = ' ';
        return $Nullable;
    }

    /**
     * Set Default Value for a column
     * @param $ColumnType
     * @param $isDefaulted
     * @return string
     */
    private function setDefaultValue($ColumnType, $isDefaulted){
        $DefaultValues = [
            'int'           =>  '0',
            'tinyint'       =>  '0',
            'smallint'      =>  '0',
            'mediumint'     =>  '0',
            'bigint'        =>  '0',
            'float'         =>  '0.00',
            'double'        =>  '0.00',
            'decimal'       =>  '0.00',
            'date'          =>  '0000-00-00',
            'datetime'      =>  '0000-00-00 00:00:00',
            'timestamp'     =>  '0',
            'char'          =>  '',
            'varchar'       =>  '',
        ];
        try {
            if($isDefaulted){
                if($ColumnType == 'text' || $ColumnType == 'blob')
                    throw new Exception('You cannot set default value for '.$ColumnType);
                if(is_bool($isDefaulted))
                    if($DefaultValues[$ColumnType] == '')
                        return 'DEFAULT NULL';
                    else
                        return 'DEFAULT \''.$DefaultValues[$ColumnType].'\'';
                else
                    return 'DEFAULT \''.$isDefaulted.'\'';
            } else {
                return '';
            }

        } catch (Exception $e){
            die('<strong>'.$e->getMessage().'</strong>');
        }
    }

    /**
     * Sets AI Variable
     * @param $ColumnName
     * @return string
     */
    private function setAIStatus($ColumnName){
        if($this->AIColumn == $ColumnName)
            return '  AUTO_INCREMENT';
        else
            return '';
    }
}