<?php

class LearningpathsModule extends CWebModule
{
    /**
    * Check Learning path before activation
    *
    * @param int $lpId
    * @return array list of errors. If empty - everything is ok
    */
    public static function getLearningpathErrors($lpId){
        $result = ['errors' => [], 'ids' => []];

        $mainActivities = Yii::app()->db->createCommand()
            ->select('LMS_LP_MAINACTIVITIES.ACTIVITY_ID, START_RULE_AFTER, TITLE, ACTIVITY_TYPE, START_RULE_ALWAYS')
            ->from('LMS_LP_MAINACTIVITIES')
            ->join('LMS_ACTIVITIES', '"LMS_LP_MAINACTIVITIES".ACTIVITY_ID = "LMS_ACTIVITIES".ACTIVITY_ID')
            ->where("LP_ID = :lpId", [':lpId' => $lpId])
            ->queryAll();

        if (empty($mainActivities)) {
            return [__('No main activities found. Please assign at least one')];
        }

        //search cycle in start rule
        try {
            LearningpathsModule::checkLoops($mainActivities, $result['ids']);
        } catch (Exception $e){
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
    * Function for search cycle in main activity start rule
    *
    * @param array $mainActivities
    * @param array $loopIds for looped activities ids
    * @return void
    */
    public static function checkLoops($mainActivities, &$loopIds)
    {
        //filter $mainActivities from untied activities
        $mainActivities = array_filter($mainActivities, function($x){
                return $x['START_RULE_ALWAYS'] === DB_FALSE && $x['START_RULE_AFTER'] !== null;
            }
        );
        if(count($mainActivities) < 2) return; //can not contain loops so return without checking

        $root = null; //current root
        $actCount = count($mainActivities);
        $nodesTable = []; //array of activities hashtable with ids as indexes
        $checkedNodes = []; //array of already checked nodes of activities tree

        foreach ($mainActivities as $mainAct) {
            $nodesTable[$mainAct['ACTIVITY_ID']] = $mainAct; // transform $mainActivities array into hashtable-like array for faster search
        }

        //recursive closure - an instance of predefined PHP class Closure
        $parser = function($root, $parents, &$checkedNodes) use (&$parser, $mainActivities, &$loopIds) {
            $child = null;
            $checkedNodes[$root['ACTIVITY_ID']] = $root;

            foreach($mainActivities as $mainAct){
                if($mainAct === $root) continue;
                if($mainAct['START_RULE_AFTER'] === $root['ACTIVITY_ID']){
                    $child = $mainAct; //find all childs of current root
                    if(isset($parents[$child['ACTIVITY_ID']])){
                        $loopIds = array_keys($parents); //return array of looped activities ids
                        $loopIds[] = (int)$root['ACTIVITY_ID'];
                        throw new Exception(__('Loop found including main activity {%1}, {%2}. Please change dependency rules', $root['TITLE'], $child['TITLE']));
                    }
                    if(isset($checkedNodes[$child['ACTIVITY_ID']])) continue; //skip recursive call if child is already checked
                    $parents[$root['ACTIVITY_ID']] = $root;
                    $parser($child, $parents, $checkedNodes); //recursive call with child as new root
                }
            }
        };

        do {
            $parents = [];
            $exRoot = null;

            if(empty($root)){
                $root = current($nodesTable); //initialization of the first root
            } else {
                $exRoot = $root;
                if(isset($nodesTable[$root['START_RULE_AFTER']])){
                    $root = $nodesTable[$root['START_RULE_AFTER']]; //choose parent of current root as new root
                }
            }
            if($exRoot === $root){
                $root = current(array_diff_key($nodesTable, $checkedNodes)); //jump to another tree of hierarchically tied activities
            }
            if(isset($nodesTable[$root['START_RULE_AFTER']])){
                $parents[$root['START_RULE_AFTER']] = $nodesTable[$root['START_RULE_AFTER']]; //get parent of current root
            }

            $parser($root, $parents, $checkedNodes); //call of recursive function
        } while ($actCount > count($checkedNodes));
    }
}