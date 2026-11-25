<?php

namespace App\Modules\Admin\Business\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FinanceSummaryExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(
        private readonly array $overview,
        private readonly array $range
    ) {
    }

    public function headings(): array
    {
        return ['Indicador', 'Valor'];
    }

    public function array(): array
    {
        $metrics = $this->overview['metrics'];

        return [
            ['Período', $this->range['start']->format('d/m/Y').' a '.$this->range['end']->format('d/m/Y')],
            ['Faturamento em pedidos', $metrics['sales_total']],
            ['Recebido confirmado', $metrics['received_total']],
            ['Saldo pendente registrado', $metrics['pending_total']],
            ['Reembolsos efetivados', $metrics['refunded_total']],
            ['Recebido líquido de reembolsos', $metrics['net_received']],
            ['Taxas de entrega cobradas', $metrics['delivery_fees']],
            ['Descontos concedidos', $metrics['discounts']],
            ['Pedidos no período', $metrics['order_count']],
            ['Pedidos pagos', $metrics['paid_orders']],
            ['Pedidos com pagamento pendente', $metrics['pending_orders']],
            ['Pedidos cancelados', $metrics['canceled_orders']],
            ['Pedidos sem lançamento financeiro', $metrics['untracked_orders']],
        ];
    }
}
