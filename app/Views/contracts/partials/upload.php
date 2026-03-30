<div class="bg-white p-8 rounded-2xl shadow-sm border border-slate-200 text-center">
    <form action="" method="post" enctype="multipart/form-data" class="space-y-4">
        <div class="border-2 border-dashed border-slate-300 rounded-xl p-12 hover:border-blue-400 transition-colors cursor-pointer" onclick="document.getElementById('fileInput').click()">
            <div class="text-slate-400 mb-4">
                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
            </div>
            <p class="text-slate-600">Arraste a fatura TXT aqui ou clique para selecionar</p>
            <input type="file" name="invoice_txt" id="fileInput" class="hidden" accept=".txt" onchange="this.form.submit()">
        </div>
        <button type="button" onclick="document.getElementById('fileInput').click()" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700">Selecionar Arquivo</button>
    </form>
</div>
