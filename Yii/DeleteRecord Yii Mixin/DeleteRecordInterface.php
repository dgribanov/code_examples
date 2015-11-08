<?php
/**
 * Интерфейс, накладывающий на класс обязательство
 * реализовывать методы из трейта DeleteRecordTrait
 * и класса CActiveRecord (наследовать его).
 */
interface DeleteRecordInterface
{
    // константы необходимы для работы методов
    // из трейта DeleteRecordTrait
    const NOT_DELETED = 0;
    const DELETED = 1;

    /**
     * Интерфейс применим только к моделям (CActiveRecord)
     *
     * @param string $className
     * @return Reserve
     */
    public static function model($className = __CLASS__);

    /**
     * @return array
     */
    public function defaultScope();

    /**
     * @return boolean
     */
    public function delete();

    /**
     * @return void
     */
    public function deleteRecord();

    /**
     * @return boolean
     */
    public function isDeleted();
}