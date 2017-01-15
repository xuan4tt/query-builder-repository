<?php

namespace Ceddyg\QueryBuilderRepository;

use DB;
use File;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Repository using query builder.
 * 
 * This class return a Collection of stdClass or a simple stdClass.
 * It's faster and use less memory than Eloquent if you just want data from
 * database.
 * 
 * @author Ceddyg
 * @package Ceddyg\QueryBuilderRepository
 */
abstract class QueryBuilderRepository
{
    /**
     * The table associated with the repository.
     *
     * @var string
     */
    protected $sTable = '';
    
    /**
     * The name of the primary key.
     *
     * @var string
     */
    protected $sPrimaryKey  = 'id';
    
    /**
     * List of the Belongs to relation.
     * 
     * @var array
     */
    protected $aBelongsTo = [];
    
    /**
     * List of the Belongs to many relation.
     * 
     * @var array
     */
    protected $aBelongsToMany = [];
    
    /**
     * List of the Has many relation.
     * 
     * @var array
     */
    protected $aHasMany = [];
    
    /**
     * List of the attributes we want in the query.
     * 
     * @var array
     */
    protected $aFillForQuery = [];
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $aFillable = [];

    public function __construct()
    {
        if ($this->sTable == '')
        {
            $this->sTable = preg_replace(
                '~_repository(?!.*_repository)~', 
                '', 
                snake_case((new \ReflectionClass($this))->getShortName())
            );
        }
    }
    
    /**
     * Getter
     */    
    public function getTable()
    {
        return $this->sTable;
    }
    
    public function getPrimaryKey()
    {
        return $this->sPrimaryKey;
    }
    
    /**
     * Get all record in the database.
     * 
     * @param array $aColumns
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function all(array $aColumns = ['*'])
    {
        $this->setColumns($aColumns);
        
        $aQuery = DB::table($this->sTable)->get($aColumns);
        
        return $this->setResponse($aQuery);
    }
    
    /**
     * Get the first record in the database.
     * 
     * @param array $aColumns
     * 
     * @return stdClass
     */
    public function first(array $aColumns = ['*'])
    {
        $this->setColumns($aColumns);
        
        $aQuery = DB::table($this->sTable)->take(1)->get($aColumns);
        
        return $this->setResponse($aQuery)->first();
    }
    
    /**
     * Paginate the given query into a simple paginator.
     * 
     * @param int $iLimit
     * @param array $aColumns
     * @param string $sPageName
     * 
     * @return LengthAwarePaginator
     */
    public function paginate($iLimit = 15, array $aColumns = ['*'], $sPageName = 'page', $iPage = null)
    {
        $this->setColumns($aColumns);
        
        $aQuery = DB::table($this->sTable)
            ->paginate($iLimit, $aColumns, $sPageName, $iPage);
        
        $oQuery = $this->setResponse($aQuery);
        
        return $oQuery;
    }
    
    /**
     * Find a record with his ID.
     * 
     * @param int $id
     * @param array $aColumns
     * 
     * @return stdClass
     */
    public function find($id, array $aColumns = ['*'])
    {
        $this->setColumns($aColumns);
        
        $aQuery = DB::table($this->sTable)
            ->where($this->sPrimaryKey, $id)
            ->get($aColumns);
        
        return $this->setResponse($aQuery)->first();
    }
    
    /**
     * Find records with a given field.
     * 
     * @param string $sField
     * @param mixed $mValue
     * @param array $aColumns
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function findByField($sField, $mValue, array $aColumns = ['*'])
    {
        $this->setColumns($aColumns);
        
        $aQuery = DB::table($this->sTable)
            ->where($sField, $mValue)
            ->get($aColumns);
        
        return $this->setResponse($aQuery);
    }
    
    /**
     * Find records with a given where clause.
     * 
     * @param array $aWhere
     * @param array $aColumns
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function findWhere(array $aWhere, array $aColumns = ['*'])
    {
        $this->setColumns($aColumns);
        
        $oQuery = DB::table($this->sTable);
        
        foreach($aWhere as $iKey => $mCondition)
        {
            if(is_array($mCondition))
            {
                $oQuery = $oQuery->where($mCondition[0], $mCondition[1], $mCondition[2]);
            }
            else
            {
                $oQuery = $oQuery->where($iKey, $mCondition);
            }
        }
        
        $aQuery = $oQuery->get($aColumns);
        
        return $this->setResponse($aQuery);
    }
    
    /**
     * Find records with a given where in clause.
     * 
     * @param string $sField
     * @param array $aWhere
     * @param array $aColumns
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function findWhereIn($sField, array $aWhere, array $aColumns = ['*'])
    {
        $this->setColumns($aColumns);
        
        $aQuery = DB::table($this->sTable)
            ->whereIn($sField, $aWhere)
            ->get($aColumns);
        
        return $this->setResponse($aQuery);
    }
    
    /**
     * Find records with a given where not in clause.
     * 
     * @param string $sField
     * @param array $aWhere
     * @param array $aColumns
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function findWhereNotIn($sField, array $aWhere, array $aColumns = ['*'])
    {
        $this->setColumns($aColumns);
        
        $aQuery = DB::table($this->sTable)
            ->whereNotIn($sField, $aWhere)
            ->get($aColumns);
        
        return $this->setResponse($aQuery);
    }
    
    /**
     * Create a record.
     * 
     * We can store one record or multiple record. 
     * For exemple : $aAttributes = [
     *      'field1'    => $value1,
     *      'field2'    => $value2,
     * ] (will insert 1 record)
     * or
     * $aAttributes = [
     *      [
     *          'field1'    => $value1,
     *          'field2'    => $value2,
     *      ],
     *      [
     *          'field1'    => $value3,
     *          'field2'    => $value4,
     *      ]
     * ] (will insert 2 record)
     * 
     * @param array $aAttributes
     * 
     * @return bool|int if multiple return bool, if simple return the ID.
     */
    public function create(array $aAttributes)
    {
        if(is_array(array_values($aAttributes)[0]))
        {
            $aFormattedAttributes = [];
            
            foreach ($aAttributes as $aAttribute)
            {
                $aFormattedAttributes[] = $this->fillableFromArray($aAttribute);
            }
            
            return DB::table($this->sTable)->insert($aFormattedAttributes);
        }
        else
        {
            $aAttributes = $this->fillableFromArray($aAttributes);
            return DB::table($this->sTable)->insertGetId($aAttributes);
        }
    }
    
    /**
     * Update a record in the database.
     * 
     * @param int $id 
     * @param array $aAttribute
     * 
     * @return int ID of the record
     */
    public function update($id, array $aAttribute)
    {
        $aAttribute = $this->fillableFromArray($aAttribute);
        
        return DB::table($this->sTable)
            ->where($this->sPrimaryKey, $id)
            ->update($aAttribute);
    }
    
    /**
     * Insert or update a record matching the attributes, and fill it with values.
     * 
     * @param array $aAttribute
     * @param array $aValues
     * 
     * @return bool
     */
    public function updateOrCreate(array $aAttribute, array $aValues = [])
    {
        $aAttribute = $this->fillableFromArray($aAttribute);
        
        return DB::table($this->sTable)->updateOrInsert($aAttribute, $aValues);
    }
    
    /**
     * Delete one or many records from the database.
     * 
     * @param int|array $id
     * 
     * @return int Count of deleted records
     */
    public function delete($id)
    {
        if(is_array($id))
        {
            return DB::table($this->sTable)
                ->whereIn($this->sPrimaryKey, $id)
                ->delete();
        }
        else
        {
            return DB::table($this->sTable)
                ->where($this->sPrimaryKey, $id)
                ->delete();
        }
    }
    
    /**
     * Set a Belongs to relation.
     * 
     * @param type $sRepository
     * @param type $sForeignKey
     * 
     * @return void
     */
    public function belongsTo($sRepository, $sForeignKey = null)
    {
        $oRepository = new $sRepository();
        $sForeignKey = $sForeignKey ?: Str::snake($oRepository->getTable()).'_id';
        
        $this->aBelongsTo[] = [
            'name'          => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],
            'repository'    => $oRepository,
            'foreign_key'   => $sForeignKey
        ];
    }
    
    /**
     * Set a Belongs to many relation.
     * 
     * @param string $sRepository
     * @param string $sPivotTable
     * @param string $sForeignKey
     * @param string $sOtherForeignKey
     * 
     * @return void
     */
    public function belongsToMany(
        $sRepository, 
        $sPivotTable, 
        $sForeignKey        = null, 
        $sOtherForeignKey   = null
    )
    {
        $oRepository        = new $sRepository();
        $sForeignKey        = $sForeignKey ?: Str::snake($this->sTable).'_id';
        $sOtherForeignKey   = $sOtherForeignKey ?: Str::snake($oRepository->getTable()).'_id';
        
        $this->aBelongsToMany[] = [
            'name'              => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],
            'repository'        => $oRepository,
            'table_pivot'       => $sPivotTable,
            'foreign_key'       => $sForeignKey,
            'other_foreign_key' => $sOtherForeignKey
        ];
    }
    
    /**
     * Set a Has many relation.
     * 
     * @param string $sRepository
     * @param string $sForeignKey
     * 
     * @return void
     */
    public function hasMany($sRepository, $sForeignKey = null)
    {
        $oRepository = new $sRepository();
        $sForeignKey = $sForeignKey ?: Str::snake($this->sTable).'_id';
        
        $this->aHasMany[] = [
            'name'          => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'],
            'repository'    => $oRepository,
            'foreign_key'   => $sForeignKey
        ];
    }
    
    /**
     * Formatte the query.
     * 
     * @param array $aQuery
     * 
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    private function setResponse($mQuery)
    {
        if (!$mQuery instanceof Collection)
        {
            $mQuery = collect($mQuery);
        }

        $this->setRelations($mQuery);
        
        return $mQuery;
    }
    
    /**
     * Set relations to the records of the given query.
     * 
     * @param \Illuminate\Database\Eloquent\Collection|static[] $oQuery
     * 
     * @return void
     */
    private function setRelations(&$oQuery)
    {
        foreach($this->aBelongsTo as $relation)
        {
            $this->belongsToQuery($relation, $oQuery);
        }
        
        foreach($this->aHasMany as $relation)
        {
            $this->hasManyQuery($relation, $oQuery);
        }
        
        foreach($this->aBelongsToMany as $relation)
        {
            $this->belongsToManyQuery($relation, $oQuery);
        }
    }
    
    /**
     * Query the Belongs to relation.
     * 
     * @param array $aRelation
     * @param \Illuminate\Database\Eloquent\Collection|static[] $oQuery
     * 
     * @return void
     */
    private function belongsToQuery($aRelation, &$oQuery)
    {
        $sName          = $aRelation['name'];
        $sForeignKey    = $aRelation['foreign_key'];
        $oRepository    = $aRelation['repository'];
        
        $sPrimaryKey = $oRepository->getPrimaryKey();
        $aIdrelation = $oQuery->pluck($sForeignKey)->unique()->all();
        
        $oQueryRelation = $oRepository->findWhereIn($sPrimaryKey, $aIdrelation);
                
        $oQuery->transform(
            function ($oItem, $i) use ($sName, $sPrimaryKey, $sForeignKey, $oQueryRelation)
            {
                $oItem->$sName = $oQueryRelation
                    ->where($sPrimaryKey, $oItem->$sForeignKey)
                    ->first();
                
                return $oItem;
            }
        );
    }
    
    /**
     * Query the Belongs to many relation.
     * 
     * @param array $aRelation
     * @param \Illuminate\Database\Eloquent\Collection|static[] $oQuery
     * 
     * @return void
     */
    private function belongsToManyQuery($aRelation, &$oQuery)
    {
        $sName              = $aRelation['name'];
        $oRepository        = $aRelation['repository'];
        $sTablePivot        = $aRelation['table_pivot'];
        $sPrimaryKey        = $this->sPrimaryKey;
        $sForeignKey        = $aRelation['foreign_key'];
        $sOtherForeignKey   = $aRelation['other_foreign_key'];
        
        $aIdrelation = $oQuery->pluck($sPrimaryKey)->unique()->all();
        
        $oTablePivot = collect(
            DB::table($sTablePivot)
            ->whereIn($sForeignKey, $aIdrelation)
            ->get()
        );
        
        $aIdrelation = $oTablePivot->pluck($sOtherForeignKey)->unique()->all();
        
        $oQueryRelation = $oRepository
            ->findWhereIn($oRepository->getPrimaryKey(), $aIdrelation);
         
        $oQuery->transform(
            function ($oItem, $i) 
            use (
                $sName, 
                $oRepository, 
                $oTablePivot, 
                $sForeignKey, 
                $sPrimaryKey, 
                $sOtherForeignKey, 
                $oQueryRelation
            )
            {
                $aIds = $oTablePivot
                    ->where($sForeignKey, $oItem->$sPrimaryKey)
                    ->pluck($sOtherForeignKey)
                    ->unique()
                    ->all();
                    
                $oItem->$sName = $oQueryRelation
                    ->whereIn($oRepository->getPrimaryKey(), $aIds)
                    ->values();
                
                return $oItem;
            }
        );
    }
    
    /**
     * Query the Has many relation.
     * 
     * @param array $aRelation
     * @param \Illuminate\Database\Eloquent\Collection|static[] $oQuery
     * 
     * @return void
     */
    private function hasManyQuery(array $aRelation, &$oQuery)
    {
        $sName          = $aRelation['name'];
        $oRepository    = $aRelation['repository'];
        $sForeignKey    = $aRelation['foreign_key'];
        
        $sPrimaryKey = $this->sPrimaryKey;
        
        $aIdrelation = $oQuery->pluck($sPrimaryKey)->unique()->all();
        
        $oQueryRelation = $oRepository
            ->findWhereIn($sForeignKey, $aIdrelation);
        
        $oQuery->transform(
            function ($oItem, $i) 
            use (
                $sName,  
                $sPrimaryKey,
                $sForeignKey, 
                $oQueryRelation
            )
            {                    
                $oItem->$sName = $oQueryRelation
                    ->whereIn($sForeignKey, [$oItem->$sPrimaryKey])
                    ->values();
                
                return $oItem;
            }
        );
    }
    
    /**
     * Set columns to be use in the query.
     * 
     * Note : Relations are considered as colmuns, but not used in the query.
     * 
     * @param array $aColumns
     * 
     * @return void
     */
    private function setColumns(&$aColumns)
    {
        if (!empty($this->aFillForQuery))
        {
            if (in_array('*', $aColumns))
            {
                $aColumns = $this->aFillForQuery;
            }
            else
            {
                $aColumns = array_merge($aColumns, $this->aFillForQuery);
            }
        }
        
        foreach ($aColumns as $iKey => $column)
        {
            if (method_exists($this, $column))
            {
                $this->$column();
                
                unset($aColumns[$iKey]);
            }
        }
        
        if (empty($aColumns))
        {
            $aColumns[] = '*';
        }
        
        if (!in_array('*', $aColumns))
        {
            $aColumns[] = $this->sPrimaryKey;
            
            foreach ($this->aBelongsTo as $aBelongsTo)
            {
                $aColumns[] = $aBelongsTo['foreign_key'];
            }
        }
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array  $aAttributes
     * 
     * @return array
     */
    private function fillableFromArray(array $aAttributes)
    {
        if (count($this->aFillable) > 0) 
        {
            return array_intersect_key($aAttributes, array_flip($this->aFillable));
        }

        return $aAttributes;
    }
    
    /**
     * Get fill from a given view.
     * 
     * @param type $sView
     * 
     * @return \App\Repositories\QueryBuilderRepository
     */
    public function getFillFromView($sView)
    {
        $sContents = File::get(base_path().'/resources/views/'. $sView .'.blade.php');
        
        $this->aFillForQuery = [];
        foreach ($this->aFillable as $fillable)
        {
            if (stripos($sContents, $fillable) !== false)
            {
                $this->aFillForQuery[] = $fillable;
            }
        }
        
        return $this;
    }
    
    /**
     * Build a Json to be use with the Jquery Datatable server side.
     * 
     * @param array $aData
     * 
     * @return JsonResponse
     */
    public function datatable(array $aData)
    {
        $aColumns = array_unique(
            array_column($aData['columns'], 'data')
        );
        
        $oQuery = DB::table($this->sTable);
        
        $sSearch = $aData['search']['value'];
        
        if ($sSearch != '')
        {
            foreach ($aColumns as $sColumn)
            {
                $oQuery->orWhere($sColumn, 'like', '%'. $sSearch .'%');
            }
        }
        
        $aOrder = $aData['order'][0];
        $sOrder = $aColumns[$aOrder['column']];
        
        $oObjects = $oQuery
            ->offset((int) $aData['start'])
            ->limit((int) $aData['length'])
            ->orderBy($sOrder, $aOrder['dir'])
            ->get($aColumns);
        
        $iTotal = DB::table($this->sTable)->count();
        
        $aOutput = array_merge([
            'recordsTotal'      => $iTotal,
            'recordsFiltered'   => $iTotal,
            'data'              => $oObjects
        ], $aData);
        
        return new JsonResponse($aOutput);
    }
}