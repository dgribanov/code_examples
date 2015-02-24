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
        $mainActivities = Yii::app()->db->createCommand()
            ->select('LMS_LP_MAINACTIVITIES.ACTIVITY_ID, START_RULE_AFTER, TITLE, ACTIVITY_TYPE')
            ->from('LMS_LP_MAINACTIVITIES')
            ->join('LMS_ACTIVITIES', '"LMS_LP_MAINACTIVITIES".ACTIVITY_ID = "LMS_ACTIVITIES".ACTIVITY_ID')
            ->where("LP_ID = :lpId AND START_RULE_ALWAYS = :false AND START_RULE_AFTER IS NOT NULL", [':lpId' => $lpId, ':false' => DB_FALSE])
            ->queryAll();

        if (empty($mainActivities)) {
            return [__('No main activities found. Please assign at least one')];
        }

        //search cycle in start rule
        $result = [];
        try {
            LearningpathsModule::checkLoops($mainActivities);
        } catch (Exception $e){
            $result[] = $e->getMessage();
        }

        return $result;
    }

    /**
    * Function for search cycle in main activity start rule
    *
    * @param array $mainActivities
    * @return void
    */
    public static function checkLoops($mainActivities)
    {
        $checkedRoots = [];
        $root = null;
        $actCount = count($mainActivities);

        //recursive closure - an instance of predefined PHP class Closure
        $parser = function($root, $parents, &$checkedRoots) use (&$parser, $mainActivities) {
            $child = null;
            array_push($checkedRoots, $root);

            foreach($mainActivities as $mainAct){
                if($mainAct === $root) continue;

                if($mainAct['START_RULE_AFTER'] === $root['ACTIVITY_ID']){
                    $child = $mainAct; // find all childs of current root
                    foreach($parents as $parent){
                        if($parent['ACTIVITY_ID'] === $child['ACTIVITY_ID']){
                            throw new Exception(__('Loop found including main activity {%1}, {%2}. Please change dependency rules', $root['TITLE'], $child['TITLE']));
                        }
                    }
                    if(in_array($child, $checkedRoots)) continue; // skip recursive call if child is already checked 
                    array_push($parents, $root);
                    $parser($child, $parents, $checkedRoots); //recursive call with child as new root
                }
            }

            return;
        };

        do {
            $parents = [];
            $exRoot = null;
            if(empty($root)){
                $root = $mainActivities[0]; //initialization of the first root
            } else {
                $exRoot = $root;
                foreach($mainActivities as $mainAct){
                    if($mainAct['ACTIVITY_ID'] === $root['START_RULE_AFTER']){
                        $root = $mainAct; //choose parent of current root as new root
                        break;
                    }
                }
            }

            if($exRoot === $root){
                foreach($mainActivities as $mainAct){
                    if(in_array($mainAct, $checkedRoots)) continue;
                    $root = $mainAct; //if there isn't parent of current root choose new root from not checked roots
                    break;
                }
            }

            foreach($mainActivities as $mainAct){
                if($mainAct['ACTIVITY_ID'] === $root['START_RULE_AFTER']){
                    foreach($checkedRoots as $checkedRoot){
                        if($mainAct['ACTIVITY_ID'] === $checkedRoot['ACTIVITY_ID']){
                            throw new Exception(__('Loop found including main activity {%1}, {%2}. Please change dependency rules', $root['TITLE'], $checkedRoot['TITLE']));
                        }
                    }
                    array_push($parents, $mainAct);
                    break;
                }
            }

            $parser($root, $parents, $checkedRoots); // call of recursive function
        } while ($actCount > count($checkedRoots));
    }
}