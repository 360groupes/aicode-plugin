(function(){
  const $ = jQuery;

  const chatBox = () => $('#aicode-chat-box');
  const sendBtn = () => $('#aicode-send-btn');
  const clearBtn = () => $('#aicode-clear-btn');
  const userInput = () => $('#aicode-user-input');
  const typing = () => $('#aicode-typing');
  const timerEl = () => $('#aicode-timer');
  const codeBox = () => $('#aicode-generated-code');
  const copyBtn = () => $('#aicode-copy-btn');
  const downloadBtn = () => $('#aicode-download-btn');
  const codeViewBtn = () => $('#aicode-code-view');
  const previewViewBtn = () => $('#aicode-preview-view');
  const previewFrame = () => $('#aicode-preview');

  const desktopBtn = () => $('#aicode-desktop-btn');
  const tabletBtn  = () => $('#aicode-tablet-btn');
  const mobileBtn  = () => $('#aicode-mobile-btn');
  const deviceBtns = () => $('#aicode-device-buttons');

  const voiceBtn  = () => $('#aicode-voice-btn');
  const fileInput = () => $('#aicode-file-upload');
  const fileInfo  = () => $('#aicode-file-info');

  let timer=0, interval=null, suggestedFilename='analisis.txt';

  function startTimer(){ timer=0; interval = setInterval(()=>{ timer++; timerEl().text(`Tiempo: ${timer}s`); },1000); }
  function stopTimer(){ clearInterval(interval); }

  function appendMessage(role, message) {
    const div = $('<div/>').addClass('message').addClass(role);
    const content = $('<div/>').addClass('content').text(message);
    div.append(content);
    chatBox().append(div);
    chatBox().scrollTop(chatBox()[0].scrollHeight);
  }

  function cleanResponse(response) {
    return response.replace(/```[\s\S]*?```/g, '').trim();
  }

  function extractCode(response) {
    const codeRegex = /```(?:\w+\n)?([\s\S]*?)```/g;
    let match, code = '';
    while ((match = codeRegex.exec(response)) !== null) code += match[1] + '\n';
    return code.trim();
  }

  userInput().on('keypress', e => { if (e.key === 'Enter') sendBtn().click(); });

  sendBtn().on('click', function(){
    const text = userInput().val().trim();
    if (!text && fileInput()[0].files.length === 0) return;

    appendMessage('user', text || (fileInput()[0].files.length + ' archivo(s)'));
    typing().show(); startTimer();

    const formData = new FormData();
    formData.append('action', 'aicode_chat');
    formData.append('nonce', AICODE.nonce);
    formData.append('user_input', text);

    const files = fileInput()[0].files;
    for (let i=0; i<files.length; i++) formData.append('files[]', files[i]);

    fetch(AICODE.ajaxUrl, { method: 'POST', body: formData })
      .then(r=>r.json())
      .then(data=>{
        typing().hide(); stopTimer();
        if (!data.success) {
          appendMessage('assistant', data.data && data.data.response ? data.data.response : 'Error inesperado');
          return;
        }
        const response = data.data.response || '';
        appendMessage('assistant', cleanResponse(response));

        const code = extractCode(response);
        codeBox().text(code);
        if (window.hljs && codeBox()[0]) hljs.highlightElement(codeBox()[0]);

        if (!previewFrame().hasClass('hidden')) {
          previewFrame()[0].srcdoc = code;
        }
      })
      .catch(err=>{
        typing().hide(); stopTimer();
        appendMessage('assistant', 'Error en la solicitud: ' + err.message);
      });

    userInput().val('');
    fileInput().val('');
    fileInfo().text('');
  });

  clearBtn().on('click', function(){
    const form = new FormData();
    form.append('action', 'aicode_chat');
    form.append('nonce', AICODE.nonce);
    form.append('action_cmd', 'reset');

    fetch(AICODE.ajaxUrl, { method: 'POST', body: form })
      .then(r=>r.json())
      .then(data=>{
        chatBox().empty();
        codeBox().text('');
        previewFrame()[0].srcdoc = '';
        appendMessage('assistant', (data.success && data.data.response) ? data.data.response : 'Conversación vaciada.');
      });
  });

  copyBtn().on('click', function(){
    const code = codeBox().text();
    navigator.clipboard.writeText(code).then(()=>{ alert('Texto copiado al portapapeles.'); }, err=>{ alert('Error al copiar: ' + err); });
  });

  downloadBtn().on('click', function(){
    const code = codeBox().text();
    const filename = suggestedFilename || 'analisis.txt';
    const blob = new Blob([code], {type:'text/plain'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href=url; a.download=filename; document.body.appendChild(a); a.click();
    setTimeout(()=>{ document.body.removeChild(a); URL.revokeObjectURL(url); },0);
  });

  codeViewBtn().on('click', function(){
    codeBox().removeClass('hidden');
    previewFrame().addClass('hidden');
    codeViewBtn().prop('disabled', true);
    previewViewBtn().prop('disabled', false);
    deviceBtns().hide();
  });

  previewViewBtn().on('click', function(){
    const code = codeBox().text();
    previewFrame()[0].srcdoc = code;
    codeBox().addClass('hidden');
    previewFrame().removeClass('hidden');
    codeViewBtn().prop('disabled', false);
    previewViewBtn().prop('disabled', true);
    deviceBtns().show();
  });

  desktopBtn().on('click', function(){ previewFrame().css({width:'100%', height:'calc(100% - 40px)'}); });
  tabletBtn().on('click', function(){ previewFrame().css({width:'768px', height:'calc(100% - 40px)'}); });
  mobileBtn().on('click', function(){ previewFrame().css({width:'375px', height:'calc(100% - 40px)'}); });

  fileInput().on('change', function(){ const c = this.files.length; fileInfo().text(c ? (c + ' archivo(s) seleccionado(s)') : ''); });

  // Voz (Web Speech API) si está disponible
  try {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
      const recognition = new SpeechRecognition();
      recognition.lang = 'es-ES'; recognition.interimResults = false; recognition.maxAlternatives = 1;
      voiceBtn().on('click', ()=> recognition.start());
      recognition.addEventListener('result', e=>{
        const transcript = e.results[0][0].transcript;
        userInput().val(userInput().val() + transcript + ' ');
      });
      recognition.addEventListener('end', ()=> recognition.stop());
    }
  } catch(e){}
})();
