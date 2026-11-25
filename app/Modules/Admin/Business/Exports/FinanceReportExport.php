<?php

namespace App\Modules\Admin\Business\Exports;

use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinanceReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Builder $reportQuery,
        private readonly string $type
    ) {
    }

    public function query(): Builder
    {
        return $this->reportQuery;
    }

    public function headings(): array
    {
        return $this->type === 'orders'
            ? [
                'Pedido',
                'Data',
                'Cliente',
                'Status operacional',
                'Status financeiro',
                'Subtotal',
                'Descontos',
                'Taxa de entrega',
                'Total do pedido',
                'Recebido',
                'Reembolsado',
                'Forma(s) de pagamento',
            ]
            : [
                'Pedido',
                'Data da tentativa',
                'Data do recebimento',
                'Cliente',
                'Forma de pagamento',
                'Referência',
                'Valor da tentativa',
                'Status da tentativa',
                'Status financeiro do pedido',
                'Status operacional',
            ];
    }

    public function map($row): array
    {
        if ($this->type === 'orders') {
            return [
                $this->safeText($row->number ?: (string) $row->id),
                $row->placed_at,
                $this->safeText($row->customer_name),
                $this->safeText($row->status),
                $this->safeText($row->finance_status),
                $row->subtotal,
                $row->discount,
                $row->delivery_fee,
                $row->total,
                $row->paid_amount,
                $row->refunded_amount,
                $this->safeText($row->payment_methods),
            ];
        }

        return [
            $this->safeText($row->number ?: (string) $row->order_id),
            $row->created_at,
            $row->paid_at,
            $this->safeText($row->customer_name),
            $this->safeText($row->method ?: 'Não informado'),
            $this->safeText($row->reference),
            $row->amount,
            $this->safeText($row->status),
            $this->safeText($row->finance_status),
            $this->safeText($row->order_status),
        ];
    }

    private function safeText(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value)
            ? "'".$value
            : $value;
    }
}
