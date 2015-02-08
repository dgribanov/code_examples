<?php
/**
* Global Exception handler class.
*/

class ExceptionHandler
{
    /**
    * Global Exception handler.
    * Call static from Yii application onException() event handler.
    * @param CExceptionEvent $event
    */
    public static function handler (CExceptionEvent $event){
        if(ITK_SITOS_ENV === "PRODUCTION"){
            $exception = $event->exception;
            $controller = new CController(null);
            $controller->layout = 'empty';

            if($exception->statusCode === 404){
                $errorMessage = date(TIMESTAMP_FORMAT) . ": " .__('Route not found') . ': ' . Yii::app()->request->hostInfo . Yii::app()->request->url;
                $errorViewPath = '//errors/error_404';
                http_response_code(404);
            } else {
                $errorMessage = date(TIMESTAMP_FORMAT) . ": " . __('Sorry, something went terribly wrong. The last message from system was "{%1}"', $exception->getMessage()) . '.';
                $errorViewPath = '//errors/sitos_general_error';
            }

            $errors = ['errors' => [$errorMessage]];
            if(Yii::app()->request->isAjaxRequest){
                Yii::app()->end(json_encode($errors));
            } else {
                $controller->render($errorViewPath, $errors);
            }
            $event->handled = true;
        }
    }
}