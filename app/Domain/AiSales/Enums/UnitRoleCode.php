<?php

namespace App\Domain\AiSales\Enums;

enum UnitRoleCode: string
{
    case Customer = 'customer';
    case Supplier = 'supplier';
    case ProspectiveCustomer = 'prospective_customer';
    case ProspectiveSupplier = 'prospective_supplier';
    case Manufacturer = 'manufacturer';
    case Carrier = 'carrier';
    case ServiceProvider = 'service_provider';
    case Other = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function defaultLane(): BusinessLane
    {
        return match ($this) {
            self::Customer, self::ProspectiveCustomer => BusinessLane::Sales,
            self::Supplier, self::ProspectiveSupplier, self::Manufacturer => BusinessLane::Procurement,
            self::Carrier => BusinessLane::Logistics,
            self::ServiceProvider => BusinessLane::Service,
            self::Other => BusinessLane::Internal,
        };
    }

    public function allowsLane(BusinessLane $lane): bool
    {
        return match ($this) {
            self::Manufacturer => in_array($lane, [BusinessLane::Sales, BusinessLane::Procurement], true),
            self::Other => $lane === BusinessLane::Internal,
            default => $this->defaultLane() === $lane,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Клиент',
            self::Supplier => 'Поставщик',
            self::ProspectiveCustomer => 'Потенциальный клиент',
            self::ProspectiveSupplier => 'Потенциальный поставщик',
            self::Manufacturer => 'Производитель',
            self::Carrier => 'Перевозчик',
            self::ServiceProvider => 'Исполнитель услуг',
            self::Other => 'Другая роль',
        };
    }
}
