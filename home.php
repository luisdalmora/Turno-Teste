<?php
require_once __DIR__ . '/config.php';

// Bloco de verificação de login e inicialização de sessão
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Sessão expirada ou acesso negado.', 'action' => 'redirect', 'location' => 'index.html']);
        exit;
    }
    header('Location: index.html?erro=' . urlencode('Acesso negado. Faça login primeiro.'));
    exit;
}

// Geração de tokens CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token']; // Usado para turnos e outras ações gerais do dashboard

if (empty($_SESSION['csrf_token_backup'])) { // Token específico para backup
    $_SESSION['csrf_token_backup'] = bin2hex(random_bytes(32));
}
$csrfTokenBackup = $_SESSION['csrf_token_backup'];


if (empty($_SESSION['csrf_token_implantacoes'])) {
    $_SESSION['csrf_token_implantacoes'] = bin2hex(random_bytes(32));
}
$csrfTokenImplantacoes = $_SESSION['csrf_token_implantacoes'];

if (empty($_SESSION['csrf_token_obs_geral'])) {
    $_SESSION['csrf_token_obs_geral'] = bin2hex(random_bytes(32));
}
$csrfTokenObsGeral = $_SESSION['csrf_token_obs_geral'];

// Informações do usuário e data
$nomeUsuarioLogado = $_SESSION['usuario_nome_completo'] ?? 'Usuário';
$emailUsuarioLogado = $_SESSION['usuario_email'] ?? 'primary'; 

$anoExibicao = date('Y');
$mesExibicao = date('m');
$nomesMeses = ["", "Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
$nomeMesExibicao = $nomesMeses[(int)$mesExibicao] ?? '';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Gestão de Turnos</title>
  <link href="src/output.css" rel="stylesheet"> 
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script defer src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    #employee-hours-chart-container { height: 280px; position: relative; }
    #shifts-table-main input, #shifts-table-main select,
    #implantacoes-table-main input, #implantacoes-table-main select { min-width: 80px; }

    .modal-backdrop {
        position: fixed; inset: 0; background-color: rgba(0, 0, 0, 0.5);
        display: flex; align-items: center; justify-content: center;
        z-index: 1050; opacity: 0; transition: opacity 0.3s ease-out; pointer-events: none;
    }
    .modal-backdrop.show { opacity: 1; pointer-events: auto; }
    .modal-content-backup {
        background-color: white; padding: 2rem; border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
        width: 90%; max-width: 400px; text-align: center;
        transform: scale(0.95); transition: transform 0.3s ease-out;
    }
    .modal-backdrop.show .modal-content-backup { transform: scale(1); }
    .progress-bar-container {
        width: 100%; background-color: #e5e7eb; border-radius: 0.25rem; overflow: hidden; margin-top: 1rem; margin-bottom: 1rem;
    }
    .progress-bar {
        width: 0%; 
        height: 1.25rem; background-color: #3b82f6; /* Azul */
        text-align: center; line-height: 1.25rem; color: white; font-size: 0.75rem;
        transition: width 0.5s ease;
    }
    .progress-bar.indeterminate {
        background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent);
        background-size: 1rem 1rem;
        animation: progress-bar-stripes 1s linear infinite;
        width: 100% !important; 
    }
    @keyframes progress-bar-stripes {
        from { background-position: 1rem 0; }
        to { background-position: 0 0; }
    }
  </style>
</head>
<body class="bg-gray-100 font-poppins text-gray-700">

  <div id="backup-modal-backdrop" class="modal-backdrop">
    <div class="modal-content-backup">
      <h3 id="backup-modal-title" class="text-lg font-medium text-gray-900">Backup do Banco de Dados</h3>
      <div id="backup-modal-message" class="mt-2 text-sm text-gray-600">
        Iniciando o processo de backup...
      </div>
      <div class="progress-bar-container" id="backup-progress-bar-container" style="display: none;">
        <div class="progress-bar" id="backup-progress-bar">0%</div>
      </div>
      <div class="mt-4">
        <button type="button" id="backup-modal-close-btn" class="inline-flex justify-center rounded-md border border-transparent bg-blue-100 px-4 py-2 text-sm font-medium text-blue-900 hover:bg-blue-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2" style="display: none;">
          Fechar
        </button>
         <a href="#" id="backup-download-link" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i data-lucide="download" class="w-4 h-4 mr-2"></i> Baixar Backup
        </a>
      </div>
    </div>
  </div>

  <div class="flex h-screen overflow-hidden">
    <aside class="w-64 bg-gradient-to-b from-blue-800 to-blue-700 text-indigo-100 flex flex-col flex-shrink-0">
      <div class="h-16 flex items-center px-4 md:px-6 border-b border-white/10">
        <i data-lucide="gauge-circle" class="w-7 h-7 md:w-8 md:h-8 mr-2 md:mr-3 text-white"></i>
        <h2 class="text-lg md:text-xl font-semibold text-white">Sim Posto</h2>
      </div>
      <nav class="flex-grow p-2 space-y-1">
        <a href="home.php" class="flex items-center px-3 py-2.5 rounded-lg bg-blue-600 text-white font-medium text-sm">
          <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3"></i> Dashboard
        </a>
        <a href="relatorio_turnos.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
          <i data-lucide="file-text" class="w-5 h-5 mr-3"></i> Relatórios
        </a>
        <a href="gerenciar_colaboradores.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
          <i data-lucide="users" class="w-5 h-5 mr-3"></i> Colaboradores
        </a>
        <a href="calendario_fullscreen.php" class="flex items-center px-3 py-2.5 rounded-lg hover:bg-blue-500 hover:text-white transition-colors text-sm">
          <i data-lucide="calendar-days" class="w-5 h-5 mr-3"></i> Google Calendar
        </a>
      </nav>
      <div class="p-2 border-t border-white/10">
        <input type="hidden" id="csrf-token-backup" value="<?php echo htmlspecialchars($csrfTokenBackup); ?>">

        <div class="px-2 py-1 space-y-1.5">
            <a href="#" id="backup-db-btn" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-teal-500 hover:bg-teal-600 text-white font-medium transition-colors text-sm">
                <i data-lucide="database-backup" class="w-4 h-4 mr-2"></i> Backup Banco de Dados
            </a>
            
            <a href="google_auth_redirect.php" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-green-500 hover:bg-green-600 text-white font-medium transition-colors text-sm" id="connect-gcal-btn" style="display: none;">
                <i data-lucide="link" class="w-4 h-4 mr-2"></i> Conectar Google
            </a>
            <button id="disconnect-gcal-btn" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-gray-800 font-medium transition-colors text-sm" style="display: none;">
                <i data-lucide="unlink-2" class="w-4 h-4 mr-2"></i> Desconectar Google
            </button>
        </div>
        <div class="px-2 py-1 mt-1.5">
            <a href="logout.php" id="logout-link" class="flex items-center justify-center w-full px-3 py-2 rounded-lg bg-red-500 hover:bg-red-600 text-white font-medium transition-colors text-sm">
                <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Sair
            </a>
        </div>
      </div>
    </aside>

    <div class="flex-grow flex flex-col overflow-y-auto">
      <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 md:px-6 flex-shrink-0">
        <div class="flex items-center">
          <i data-lucide="fuel" class="w-6 h-6 md:w-7 md:h-7 mr-2 md:mr-3 text-blue-600"></i>
          <h1 class="text-md md:text-lg font-semibold text-gray-800">Sim Posto - Gestão de Turnos</h1>
        </div>
        <div id="user-info" class="flex items-center text-sm font-medium text-gray-700">
          Olá, <?php echo htmlspecialchars($nomeUsuarioLogado); ?>
          <i data-lucide="circle-user-round" class="w-5 h-5 md:w-6 md:h-6 ml-2 text-blue-600"></i>
        </div>
      </header>

      <main class="flex-grow p-4 md:p-6">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6">

          <section class="xl:col-span-1 bg-white p-4 md:p-5 rounded-lg shadow space-y-4 md:space-y-5">
            <div>
              <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <i data-lucide="calendar-check-2" class="w-5 h-5 mr-2 text-blue-600"></i> Calendário (Google)
              </h2>
              <div class="border border-gray-200 rounded-md overflow-hidden">
                <iframe src="https://calendar.google.com/calendar/embed?src=<?php echo urlencode($emailUsuarioLogado); ?>&src=pt-br.brazilian%23holiday%40group.v.calendar.google.com&ctz=America%2FSao_Paulo"
                  style="border:0" width="100%" height="320" frameborder="0" scrolling="no"></iframe>
              </div>
            </div>
            
            <div>
              <h3 class="text-sm md:text-base font-semibold text-gray-700 mb-2 flex items-center justify-center py-2 border-b border-gray-200" id="feriados-mes-ano-display">
                 <i data-lucide="calendar-heart" class="w-4 h-4 mr-2 text-blue-600"></i> Feriados - Carregando...
              </h3>
              <div class="max-h-40 overflow-y-auto text-xs md:text-sm">
                <table id="feriados-table" class="w-full">
                  <thead class="sticky top-0 bg-blue-600 text-white z-10">
                    <tr>
                      <th class="p-2 text-left font-semibold uppercase text-xs">DATA</th>
                      <th class="p-2 text-left font-semibold uppercase text-xs">OBSERVAÇÃO</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr><td colspan="2" class="p-2 text-center text-gray-500">Carregando...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </section>

          <section class="xl:col-span-2 bg-white p-4 md:p-5 rounded-lg shadow space-y-4 md:space-y-5">
            <div>
              <input type="hidden" id="csrf-token-shifts" value="<?php echo htmlspecialchars($csrfToken); ?>">
              <div class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-gray-200 gap-2">
                <button id="prev-month-button" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center w-full sm:w-auto justify-center">
                    <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i> Anterior
                </button>
                <h2 id="current-month-year-display" data-year="<?php echo $anoExibicao; ?>" data-month="<?php echo $mesExibicao; ?>" class="text-base md:text-lg font-semibold text-gray-800 flex items-center order-first sm:order-none text-center">
                    <i data-lucide="list-todo" class="w-5 h-5 mr-2 text-blue-600"></i> Turnos - <?php echo htmlspecialchars($nomeMesExibicao . ' ' . $anoExibicao); ?>
                </h2>
                <button id="next-month-button" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center w-full sm:w-auto justify-center">
                    Próximo <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                </button>
              </div>
              <div class="flex flex-wrap gap-2 mb-3">
                <button id="add-shift-row-button" class="px-3 py-1.5 text-xs font-medium text-white bg-green-500 hover:bg-green-600 rounded-md flex items-center"><i data-lucide="plus-circle" class="w-4 h-4 mr-1.5"></i> Adicionar Turno</button>
                <button id="delete-selected-shifts-button" class="px-3 py-1.5 text-xs font-medium text-white bg-red-500 hover:bg-red-600 rounded-md flex items-center"><i data-lucide="trash-2" class="w-4 h-4 mr-1.5"></i> Excluir</button>
                <button id="save-shifts-button" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md flex items-center"><i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar</button>
              </div>
              <div class="overflow-x-auto max-h-80 text-xs md:text-sm">
                 <table id="shifts-table-main" class="w-full min-w-[500px]">
                    <thead class="sticky top-0 bg-blue-600 text-white z-10">
                      <tr>
                        <th class="p-2 w-10 text-center"><input type="checkbox" id="select-all-shifts" title="Selecionar Todos" class="form-checkbox h-3.5 w-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"></th>
                        <th class="p-2 text-left font-semibold uppercase text-xs">Dia (dd/Mês)</th>
                        <th class="p-2 text-left font-semibold uppercase text-xs">Início</th>
                        <th class="p-2 text-left font-semibold uppercase text-xs">Fim</th>
                        <th class="p-2 text-left font-semibold uppercase text-xs">Colaborador</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200"></tbody>
                </table>
              </div>
            </div>

            <div class="pt-4">
              <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 flex items-center pb-3 border-b border-gray-200">
                <i data-lucide="bar-chart-3" class="w-5 h-5 mr-2 text-blue-600"></i>
                Resumo de Horas (<span id="employee-summary-period"><?php echo htmlspecialchars($nomeMesExibicao); ?></span>)
              </h2>
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
                <div class="max-h-60 overflow-y-auto text-xs md:text-sm">
                    <table id="employee-summary-table" class="w-full">
                        <thead class="sticky top-0 bg-blue-600 text-white z-10">
                        <tr>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Colaborador</th>
                            <th class="p-2 text-left font-semibold uppercase text-xs">Total Horas</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200"></tbody>
                    </table>
                </div>
                <div id="employee-hours-chart-container" class="w-full">
                  <canvas id="employee-hours-chart"></canvas>
                </div>
              </div>
            </div>
          </section>
          
          <section class="xl:col-span-3 bg-white p-4 md:p-5 rounded-lg shadow">
            <input type="hidden" id="csrf-token-implantacoes" value="<?php echo htmlspecialchars($csrfTokenImplantacoes); ?>">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-3 pb-3 border-b border-gray-200 gap-2">
              <button id="prev-month-implantacoes-button" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center w-full sm:w-auto justify-center">
                  <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i> Anterior
              </button>
              <h2 id="current-month-year-implantacoes-display" class="text-base md:text-lg font-semibold text-gray-800 flex items-center order-first sm:order-none text-center">
                  <i data-lucide="settings-2" class="w-5 h-5 mr-2 text-blue-600"></i> Implantações - <?php echo htmlspecialchars($nomeMesExibicao . ' ' . $anoExibicao); ?>
              </h2>
              <button id="next-month-implantacoes-button" class="px-3 py-1.5 text-xs font-medium text-white bg-gray-500 hover:bg-gray-600 rounded-md flex items-center w-full sm:w-auto justify-center">
                  Próximo <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
              </button>
            </div>
            <div class="flex flex-wrap gap-2 mb-3">
              <button id="add-implantacao-row-button" class="px-3 py-1.5 text-xs font-medium text-white bg-green-500 hover:bg-green-600 rounded-md flex items-center"><i data-lucide="plus-circle" class="w-4 h-4 mr-1.5"></i> Adicionar</button>
              <button id="delete-selected-implantacoes-button" class="px-3 py-1.5 text-xs font-medium text-white bg-red-500 hover:bg-red-600 rounded-md flex items-center"><i data-lucide="trash-2" class="w-4 h-4 mr-1.5"></i> Excluir</button>
              <button id="save-implantacoes-button" class="px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md flex items-center"><i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar</button>
            </div>
            <div class="overflow-x-auto max-h-72 text-xs md:text-sm">
               <table id="implantacoes-table-main" class="w-full min-w-[500px]">
                  <thead class="sticky top-0 bg-blue-600 text-white z-10">
                    <tr>
                      <th class="p-2 w-10 text-center"><input type="checkbox" id="select-all-implantacoes" title="Selecionar Todos" class="form-checkbox h-3.5 w-3.5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"></th>
                      <th class="p-2 text-left font-semibold uppercase text-xs">Dia Início</th>
                      <th class="p-2 text-left font-semibold uppercase text-xs">Dia Fim</th>
                      <th class="p-2 text-left font-semibold uppercase text-xs">Observações</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr><td colspan="4" class="p-2 text-center text-gray-500">Carregando...</td></tr>
                  </tbody>
              </table>
            </div>
          </section>

          <section class="xl:col-span-3 bg-white p-4 md:p-5 rounded-lg shadow">
            <h2 class="text-base md:text-lg font-semibold text-gray-800 mb-3 flex items-center">
                <i data-lucide="notebook-pen" class="w-5 h-5 mr-2 text-blue-600"></i> Observações Gerais
            </h2>
            <input type="hidden" id="csrf-token-obs-geral" value="<?php echo htmlspecialchars($csrfTokenObsGeral); ?>">
            <textarea id="observacoes-gerais-textarea" rows="3" placeholder="Digite aqui qualquer informação importante..." class="form-textarea w-full p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500 text-sm"></textarea>
            <button id="salvar-observacoes-gerais-btn" class="mt-3 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md flex items-center">
                <i data-lucide="save" class="w-4 h-4 mr-1.5"></i> Salvar Observações
            </button>
          </section>

        </div>
      </main>
    </div>
  </div>
  
  <script src="script.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
      const urlParams = new URLSearchParams(window.location.search);
      const gcalStatus = urlParams.get('gcal_status');
      const gcalMsg = urlParams.get('gcal_msg');
      if (gcalStatus === 'success') {
          if(typeof showToast === 'function') showToast('Google Calendar conectado com sucesso!', 'success');
          localStorage.setItem('gcal_connected_simposto', 'true');
      } else if (gcalStatus === 'error') {
          if(typeof showToast === 'function') showToast('Falha conexão GCal: ' + (decodeURIComponent(gcalMsg || '') || 'Tente novamente.'), 'error');
          localStorage.removeItem('gcal_connected_simposto');
      } else if (gcalStatus === 'disconnected') {
          if(typeof showToast === 'function') showToast('Google Calendar desconectado.', 'info');
          localStorage.removeItem('gcal_connected_simposto');
      }
      if(typeof checkGCalConnectionStatus === 'function') checkGCalConnectionStatus();

      if (gcalStatus || gcalMsg) { 
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
      }
    });
  </script>
</body>
</html>