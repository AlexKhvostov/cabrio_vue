<?php
/**
 * Модель LinkUserCar — связь пользователя и автомобиля (таблица link_user_cars).
 *
 * Назначение:
 *   Представляет связь между пользователем и автомобилем (владелец, пассажир и т.д.).
 *   Используется для определения прав, истории владения, отображения списка машин пользователя.
 *
 * Ключевые поля:
 *   - user_id: FK на users
 *   - car_id: FK на cars
 *   - role_id: FK на ref_roles (роль пользователя относительно машины)
 *
 * Связи:
 *   - User (user_id → users.id)
 *   - Car (car_id → cars.id)
 *   - Role (role_id → ref_roles.id)
 *
 * Пример использования:
 *   $link = LinkUserCar::find($user_id, $car_id);
 *   $newLink = LinkUserCar::create([...]);
 */
class LinkUserCar {
    public $user_id;
    public $car_id;
    public $role_id;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти связь по user_id и car_id
     */
    public static function find($user_id, $car_id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM link_user_cars WHERE user_id = ? AND car_id = ?');
        $stmt->execute([$user_id, $car_id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Создать новую связь
     */
    public static function create($data) {
        // ... реализация вставки в БД
    }

    /**
     * Удалить связь
     */
    public function delete() {
        // ... реализация удаления из БД
    }
} 