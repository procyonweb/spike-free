<div wire:init="loadInvoices">
    <div class="bg-white shadow overflow-hidden sm:rounded-md">
        <div class="bg-white px-4 py-5 border-b border-gray-200 sm:px-6">
            <div class="-ml-4 -mt-4 flex justify-between items-center flex-wrap sm:flex-nowrap">
                <div class="ml-4 mt-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">{{ __('spike::translations.invoices') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">{{ __('spike::translations.invoices_description') }}</p>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-300">
                <tbody class="overflow-hidden divide-y divide-gray-200 bg-white">
                @php /** @var \Opcodes\Spike\SpikeInvoice $invoice */ @endphp
                @foreach($invoices as $invoice)
                    <tr>
                        <td class="whitespace-nowrap pl-4 pr-3 py-2 sm:pl-6 text-sm font-medium text-gray-900">{{ $invoice->total }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-900">
                            @if($invoice->status)
                                <span class="inline-block w-full text-center px-2 py-1 rounded-md bg-green-50 text-green-700 text-xs font-medium">{{ $invoice->status }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-600 w-full">{{ $invoice->number }}</td>
                        <td class="whitespace-nowrap px-2 py-2 text-sm text-gray-600">{{ $invoice->date->translatedFormat(config('spike.date_formats.invoice_date', 'F j, Y')) }}</td>
                        <td class="relative whitespace-nowrap py-2 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <a href="{{ route('spike.invoices.download-invoice', [$invoice->id, 'filename' => $invoice->number]) }}"
                               class="text-brand hover:opacity-80 cursor-pointer"
                            >{{ __('spike::translations.download') }}<span class="sr-only">, {{ $invoice->number }}</span></a>
                        </td>
                    </tr>
                @endforeach

                @if(!$shouldLoadInvoices)
                    <tr>
                        <td colspan="5" class="flex items-center justify-center px-4 py-4 sm:px-6 text-sm text-gray-600">
                            <x-spike::shared.spinner class="size-4 mr-2" />
                            {{ __('spike::translations.loading') }}
                        </td>
                    </tr>
                @elseif($invoices->isEmpty())
                    <tr>
                        <td colspan="5" class="flex items-center justify-center px-4 py-4 sm:px-6 text-sm text-gray-600">
                            {{ __('spike::translations.invoices_empty') }}
                        </td>
                    </tr>
                @endempty
                </tbody>
            </table>
        </div>
    </div>
</div>
