<?php
/**
 * Модель CarBrand — справочник марок автомобилей (таблица ref_car_brands).
 *
 * Назначение:
 *   Представляет марку автомобиля (BMW, Mercedes и т.д.).
 *   Используется для фильтрации, создания и отображения автомобилей.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - brand: название марки
 *
 * Связи:
 *   - Cars (cars.car_brand_id → ref_car_brands.id)
 *
 * Пример использования:
 *   $brand = CarBrand::findById(1);
 *   $brand = CarBrand::findByBrand('BMW');
 */
class CarBrand {
    public $id;
    public $brand;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти марку по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_car_brands WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти марку по названию
     */
    public static function findByBrand($brand) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_car_brands WHERE brand = ?');
        $stmt->execute([$brand]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }
} 