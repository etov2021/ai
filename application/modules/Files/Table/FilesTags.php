<?php

namespace Files\Table;

use Micro\Database\Table\TableAbstract;



class FilesTags extends TableAbstract
{

    protected $_name = 'FilesTags';
    /**
     * 
     * @param unknown $target
     * @param unknown $tags
     * @return \Micro\Database\Table\Row\RowAbstract|NULL
     */
    public function save($target,$targetId, $tags)
    {

            $row = $this->fetchRow($this->select()->where('target = ?' , $target));

            if(!$row) {
                $row = $this->createRow();
            }

            if(!empty($tags)) {
                $tags= array_map('strtoupper', $tags);
            }
            
            
            $row->tags = implode(",", $tags);
            $row->target = $target;
            $row->targetId = $targetId;


            $row->save();

        
        return $row;
    }
    /**
     * 
     * @param unknown $target
     * @return array|array|\Micro\Database\Table\Row\RowAbstract|NULL
     */
    public function getTags($target,$targetId)
    {

        $row = $this->fetchRow($this->select()->where('target = ?' , $target)->where('targetId = ?',$targetId));

        if($row) {
            return explode(",", $row->tags);
        } else {
            $row = [];
        }
                   
        return $row;
    }

    
 
}