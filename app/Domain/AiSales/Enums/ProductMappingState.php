<?php

namespace App\Domain\AiSales\Enums;

enum ProductMappingState: string
{
    case NotApplicable = 'not_applicable';
    case Mapped = 'mapped';
    case LegacyUnreconciled = 'legacy_unreconciled';
    case MissingProductMapping = 'missing_product_mapping';
    case AmbiguousProductMapping = 'ambiguous_product_mapping';
    case ProductScopeMismatch = 'product_scope_mismatch';

    public function requiresReview(): bool
    {
        return ! in_array($this, [self::NotApplicable, self::Mapped], true);
    }
}
