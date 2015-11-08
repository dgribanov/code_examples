<?php
/**
 * Трейт, который инкапсулирует логику удаления записи из БД.
 * Используется только вместе с интерфейсом DeleteRecordInterface.
 */
trait DeleteRecordTrait
{
    /**
     * @return array
     */
    public function defaultScope() {
        return [
            'condition' => "is_deleted <> 1",
        ];
    }

    /**
     * Переопределённый метод из CActiveRecord.
     * Не удаляем запись из БД, а переводим её в статус удалённых (is_deleted = 1)
     *
     * @throws CDbException if the record is new
     * @return boolean whether the deletion is successful
     */
    public function delete()
    {
        if(!$this->getIsNewRecord())
        {
            Yii::trace(get_class($this).'.delete()','system.db.ar.CActiveRecord');
            if($this->beforeDelete())
            {
                $this->deleteRecord();
                $result = $this->save();
                $this->afterDelete();
                return $result;
            } else {
                return false;
            }
        } else {
            throw new CDbException(Yii::t('yii','The active record cannot be deleted because it is new.'));
        }
    }

    /**
     * Метод, отвечающий за детали удаления записи.
     * При необходимости переопределяется в классе, использующем трейт.
     *
     * @return void
     */
    public function deleteRecord()
    {
        $this->is_deleted = self::DELETED;
    }

    /**
     * Проверить удалена ли запись
     *
     * @return boolean
     */
    public function isDeleted()
    {
        if ((int)($this->is_deleted) === self::DELETED) {
            return true;
        }
        return false;
    }
}