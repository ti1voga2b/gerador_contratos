<form action="" method="post">
    <input type="hidden" name="_csrf" value="<?= $escape($csrfToken ?? '') ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200">
                <h3 class="font-bold text-slate-900 mb-4">Dados do Cliente</h3>
                <div class="space-y-3 text-sm">
                    <p><span class="text-slate-500">Nome:</span><br><strong><?= $escape($extractedData['clientName'] ?? '') ?></strong></p>
                    <p><span class="text-slate-500">CNPJ:</span><br><strong><?= $escape($extractedData['clientCNPJ'] ?? '') ?></strong></p>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-slate-200 space-y-4">
                <h3 class="font-bold text-slate-900">Configuracoes</h3>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Rede de Destino</label>
                    <select name="operator" id="operatorSelect" class="w-full border-slate-300 rounded-lg text-sm" onchange="filterPlans()">
                        <option value="VIVO">VIVO (via TELECALL)</option>
                        <option value="TIM">TIM (via SURF)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Fidelidade</label>
                    <select name="fidelity" class="w-full border-slate-300 rounded-lg text-sm">
                        <option value="none">Sem Fidelidade</option>
                        <option value="12">12 Meses</option>
                        <option value="24">24 Meses</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Condicoes Comerciais</label>
                    <textarea name="commercial_terms" rows="4" class="w-full border-slate-300 rounded-lg text-sm" placeholder="Observacoes adicionais para o contrato"></textarea>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="sla" id="slaCheck" class="rounded text-blue-600">
                    <label for="slaCheck" class="text-sm text-slate-700">Incluir SLA Customizado</label>
                </div>
                <button type="submit" name="action" value="generate_term" class="w-full bg-green-600 text-white py-3 rounded-lg font-bold hover:bg-green-700 shadow-md transition-all">GERAR CONTRATO (PDF)</button>
                <a href="index.php?reset=1" class="block text-center text-sm text-slate-500 hover:underline">Carregar outra fatura</a>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Numero</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase">Novo Plano VOGA</th>
                            <th class="p-4 text-xs font-bold text-slate-500 uppercase text-right">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach (($extractedData['lines'] ?? []) as $idx => $line): ?>
                            <tr>
                                <td class="p-4 text-sm font-medium text-slate-900"><?= $escape($line['number'] ?? '') ?></td>
                                <td class="p-4">
                                    <select name="selected_plans[<?= (int) $idx ?>]" class="plan-select w-full border-slate-200 rounded-lg text-xs" onchange="updateTotal()">
                                        <option value="">Selecione um plano...</option>
                                        <?php foreach ($plans as $plan): ?>
                                            <option
                                                value="<?= $escape($plan['network'] . ':' . $plan['id']) ?>"
                                                data-network="<?= $escape($plan['network']) ?>"
                                                data-price="<?= $escape($plan['price']) ?>"
                                            >
                                                <?= $escape($plan['provider']) ?> (R$ <?= $escape(number_format((float) $plan['price'], 2, ',', '.')) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <option
                                            value="0"
                                            data-network="cancelar"
                                            data-price="00"
                                            style="display:block"
                                        >
                                        Cancelar
                                        </option>
                                    </select>
                                </td>
                                <td class="p-4 text-sm font-bold text-blue-600 text-right line-price">R$ 0,00</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-blue-50">
                        <tr>
                            <td colspan="2" class="p-4 text-sm font-bold text-blue-900 text-right">TOTAL DE LINHAS:</td>
                            <td id="lineCount" class="p-4 text-lg font-black text-blue-900 text-right"><?= count($extractedData['lines'] ?? []) ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="p-4 text-sm font-bold text-blue-900 text-right">NOVO TOTAL MENSAL:</td>
                            <td id="grandTotal" class="p-4 text-lg font-black text-blue-900 text-right">R$ 0,00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</form>

<script>
    function filterPlans() {
        const network = document.getElementById('operatorSelect').value;
        document.querySelectorAll('.plan-select').forEach(select => {
            Array.from(select.options).forEach(option => {
                if (option.value === '') {
                    return;
                }

                option.style.display = option.getAttribute('data-network') === network || option.getAttribute('data-network') === 'cancelar'
                    ? 'block'
                    : 'none';
            });

            const selected = select.options[select.selectedIndex];
            if (selected && selected.value !== '' && selected.style.display === 'none') {
                select.value = '';
            }
        });

        updateTotal();
    }

    function updateTotal() {
        let total = 0;

        document.querySelectorAll('.plan-select').forEach(select => {
            const option = select.options[select.selectedIndex];
            const price = option && option.value !== '' ? parseFloat(option.getAttribute('data-price')) : 0;
            total += price;
            select.closest('tr').querySelector('.line-price').innerText = 'R$ ' + price.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
        });

        document.getElementById('grandTotal').innerText = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }

    window.onload = filterPlans;
</script>
