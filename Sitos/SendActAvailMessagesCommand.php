<?php

Yii::import('modules.backend.learningpaths.LearningpathsModule');
Yii::import('modules.backend.coach.CoachModule');
Yii::import('modules.backend.lmslos.LmslosModule');
Yii::import('modules.backend.activations.ActivationsModule');

/**
* Console command for sending coach messages about activities availability.
* Should be run by Cron every day.
*/
class SendActAvailMessagesCommand extends CConsoleCommand
{
    public function run($args) 
    {
        $lps = (new Learningpath())->findAllByAttributes(['ACTIVATED' => DB_TRUE]);
        foreach ($lps as $lp) {
            $lpId = $lp->LP_ID;
            $mainActivities = (new MainActivity())->findAllByAttributes(['LP_ID' => $lpId]);
            $usersIds = LearningpathsModule::getCurrentParticipants($lpId);
            $tutorsIds = array_diff( LearningpathsModule::getLpTutors($lpId), $usersIds );

            foreach ($mainActivities as $mainActivity) {
                $isActStartToday = false;
                $isActStartInFiveDays = false;

                try {
                    $mainActivity->attachAvailabilityBehavior();
                } catch (Exception $e) {
                    Yii::log('When availability Behavior was attached to MainActivity object (ACTIVITY_ID: ' . $mainActivity->ACTIVITY_ID . ', LP_ID: ' . $mainActivity->LP_ID . ') Exception was thrown with message: ' . $e->getMessage(), CLogger::LEVEL_WARNING);
                    continue;
                }

                if ( $mainActivity->isAvailabilityDateFixed() ) {
                    foreach ( $usersIds as $userId ) {
                        $availabilityDateTime = $mainActivity->getAvailabilityDateTime($userId);
                        if ($availabilityDateTime === null) continue;

                        $messageErrors = [];
                        $availabilityDate = $availabilityDateTime->format('Y-m-d');
                        if ( $availabilityDate === date('Y-m-d') ) {
                            $messageErrors = CoachModule::onMainActivityStartsToday(new CEvent($mainActivity, ['userId' => $userId]));
                            $isActStartToday = empty($messageErrors) ? true : $isActStartToday;
                        } elseif ( $availabilityDate === date('Y-m-d', strtotime("+5 days")) ){
                            $messageErrors = CoachModule::onMainActivityStarts(new CEvent($mainActivity, ['userId' => $userId]));
                            $isActStartInFiveDays = empty($messageErrors) ? true : $isActStartInFiveDays;
                        }
                    }
                }

                if(!empty($tutorsIds)){
                    if($isActStartToday){
                        CoachModule::onMainActivityStartsToday(new CEvent($mainActivity, ['userId' => $tutorsIds]));
                    }
                    if($isActStartInFiveDays){
                        CoachModule::onMainActivityStarts(new CEvent($mainActivity, ['userId' => $tutorsIds]));
                    }
                }
            }
        }
    }
}