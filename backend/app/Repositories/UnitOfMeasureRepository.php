<?php

namespace App\Repositories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Collection;

class UnitOfMeasureRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.units-of-measure';
    protected $model = UnitOfMeasure::class;

    public function getAdminListing(string $search = ''): Collection
    {
        return $this->unitModel()
            ->newQuery()
            ->whereNull('uom_group_id')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function getOptions(array $selectedCodes = []): Collection
    {
        $selectedCodes = collect($selectedCodes)
            ->filter()
            ->map(fn ($code) => strtoupper((string) $code))
            ->values()
            ->all();

        return $this->unitModel()
            ->newQuery()
            ->whereNull('uom_group_id')
            ->where(function ($query) use ($selectedCodes) {
                $query->where('status', 'Active');

                if (! empty($selectedCodes)) {
                    $query->orWhereIn('code', $selectedCodes);
                }
            })
            ->orderBy('name')
            ->orderBy('code')
            ->get();
    }

    public function loadForAdminEdit(int $unitId): UnitOfMeasure
    {
        return $this->unitModel()
            ->newQuery()
            ->whereNull('uom_group_id')
            ->whereKey($unitId)
            ->firstOrFail();
    }

    public function createForAdmin(array $attributes): UnitOfMeasure
    {
        $attributes = $this->normalizeBaseUnitAttributes($attributes);

        $unit = $this->unitModel();
        $unit->fill($attributes);
        $unit->save();

        $this->syncBaseUnit($unit);
        UnitOfMeasure::flushQueryCache();

        return $unit;
    }

    public function updateForAdmin(UnitOfMeasure $unit, array $attributes): UnitOfMeasure
    {
        $attributes = $this->normalizeBaseUnitAttributes($attributes);

        $unit->fill($attributes);
        $unit->save();

        $this->syncBaseUnit($unit);
        UnitOfMeasure::flushQueryCache();

        return $unit;
    }

    public function deleteForAdmin(UnitOfMeasure $unit): void
    {
        $unit->delete();
        UnitOfMeasure::flushQueryCache();
    }

    protected function normalizeBaseUnitAttributes(array $attributes): array
    {
        $attributes['is_base_unit'] = (bool) ($attributes['is_base_unit'] ?? false);
        $attributes['uom_group_id'] = $attributes['uom_group_id'] ?? null;

        if ($attributes['is_base_unit']) {
            $attributes['alternate_quantity'] = 1;
            $attributes['base_quantity'] = 1;
            $attributes['conversion_factor_to_base'] = 1;
        } else {
            $alternateQuantity = (float) ($attributes['alternate_quantity'] ?? 1);
            $baseQuantity = (float) ($attributes['base_quantity'] ?? 1);

            if ($alternateQuantity > 0 && $baseQuantity > 0) {
                $attributes['conversion_factor_to_base'] = round($baseQuantity / $alternateQuantity, 6);
            }
        }

        return $attributes;
    }

    protected function syncBaseUnit(UnitOfMeasure $unit): void
    {
        if (! $unit->is_base_unit) {
            return;
        }

        $this->unitModel()
            ->newQuery()
            ->where('uom_group_id', $unit->uom_group_id)
            ->whereKeyNot($unit->getKey())
            ->update(['is_base_unit' => false]);
    }

    protected function unitModel(): UnitOfMeasure
    {
        /** @var \App\Models\UnitOfMeasure $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }
}
