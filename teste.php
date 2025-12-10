<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Teste Apple Pay — Front</title>
  <style>
    body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; padding: 24px; color: #222; }
    .apple-pay-btn { appearance: none; -webkit-appearance: none; border: 0; background: black; color: white; padding: 12px 20px; border-radius: 8px; font-size: 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 10px; }
    .hidden { display: none; }
    pre { background:#f6f8fa; padding:12px; border-radius:8px; max-height:300px; overflow:auto; }
    .status { margin-top:12px; }
  </style>
</head>
<body>

  <h1>Front Apple Pay — obter payment token</h1>
  <p>Este front detecta suporte a Apple Pay, mostra botão e: <strong>1)</strong> solicita validação do merchant ao seu backend; <strong>2)</strong> abre a UI do Apple Pay; <strong>3)</strong> quando autorizado retorna o token que você envia ao backend.</p>

  <div id="not-supported" class="status hidden">
    <strong>Apple Pay não suportado neste browser/dispositivo.</strong>
    <p>Teste em Safari em iPhone/iPad/Mac com Apple Pay configurado e página servida por HTTPS.</p>
  </div>

  <div id="apple-area" class="status hidden">
    <button id="apple-pay-button" class="apple-pay-btn">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" style="filter:invert(1)">
        <path fill="currentColor" d="M17.564 11.31c.011-1.678 1.36-2.48 1.424-2.52-0.776-1.129-1.986-1.284-2.414-1.303-1.027-0.104-2.005.602-2.526.602-.538 0-1.358-.588-2.235-.575-1.15.014-2.211.667-2.801 1.693-1.203 1.953-.307 4.85.853 6.431.555.76 1.213 1.62 2.075 1.593.82-0.027 1.129-0.52 2.113-0.52.984 0 1.264.52 2.259.502.96-0.016 1.571-0.778 2.125-1.539.672-.92.95-1.809.967-1.857-.021-.01-1.858-.714-1.847-2.832zm-2.164-4.88c.492-.59.827-1.405.737-2.22-.715.03-1.577.482-2.094 1.078-.46.532-.86 1.387-.738 2.206.783.06 1.604-.397 2.095-1.064z"/>
      </svg>
      Pagar com Apple Pay
    </button>

    <div style="margin-top:12px;">
      <label>
        Valor total:
        <input id="amount" type="number" min="0.01" step="0.01" value="10.00" style="width:100px; margin-left:8px"/>
      </label>
      <label style="margin-left:16px">
        Moeda:
        <input id="currency" type="text" value="BRL" style="width:70px; margin-left:8px"/>
      </label>
    </div>

    <div style="margin-top:12px;">
      <button id="show-debug">Mostrar/Ocultar token recebido</button>
    </div>

    <div id="result" class="hidden" style="margin-top:12px;">
      <h3>Payment token (Apple) — JSON</h3>
      <pre id="tokenJson">{ }</pre>
      <button id="copyToken">Copiar token para clipboard</button>
    </div>

    <div id="messages" style="margin-top:8px; color:#444"></div>
  </div>

<script>
/*
  INSTRUÇÕES RÁPIDAS:
  - Backend precisa expor:
    1) POST /apple-pay/validate-merchant  -> body: { validationURL: "..." }
       -> deve retornar JSON com o merchantSession recebido da Apple (object)
    2) POST /apple-pay/process-payment    -> body: { paymentToken: <object>, orderId: "...", amount: "10.00" }
       -> processa/persiste o token e captura via PagBank (seu backend já pronto).
  - Troque as URLs abaixo caso seu endpoint seja diferente.
  - Apple Pay requer HTTPS e domínio validado no Apple Developer (Merchant ID + certificado).
*/

const VALIDATE_ENDPOINT = 'http://pagoo.checkoout.local:10000/charge/apple-pay/validate-merchant';   // troque se necessário
const PROCESS_PAYMENT_ENDPOINT = '/apple-pay/process-payment'; // troque se necessário

const appleArea = document.getElementById('apple-area');
const notSupported = document.getElementById('not-supported');
const btn = document.getElementById('apple-pay-button');
const amountInput = document.getElementById('amount');
const currencyInput = document.getElementById('currency');
const messages = document.getElementById('messages');
const resultBox = document.getElementById('result');
const tokenPre = document.getElementById('tokenJson');
const showDebugBtn = document.getElementById('show-debug');
const copyBtn = document.getElementById('copyToken');

function log(msg, isError = false) {
  messages.textContent = (new Date()).toLocaleTimeString() + ' — ' + msg;
  messages.style.color = isError ? 'crimson' : '#444';
}

function supportsApplePay() {
  return window.ApplePaySession && ApplePaySession.canMakePayments && typeof ApplePaySession === 'function';
}

async function init() {
  if (!supportsApplePay()) {
    notSupported.classList.remove('hidden');
    return;
  }

  // canMakePaymentsWithActiveCard permite checar se usuario tem cartao configurado
  try {
    const canPay = await ApplePaySession.canMakePayments();
    if (!canPay) {
      log('Apple Pay disponível mas usuário pode não ter um cartão configurado.', false);
    }
  } catch(e) {
    // ignore
  }

  appleArea.classList.remove('hidden');
}

btn.addEventListener('click', async () => {
  const amount = (parseFloat(amountInput.value) || 0).toFixed(2);
  const currency = (currencyInput.value || 'BRL').toUpperCase();

  // ajuste esses valores conforme seu negócio
  const paymentRequest = {
    countryCode: 'BR',           // país da transação / configure conforme necessidade
    currencyCode: currency,
    total: {
      label: 'Minha Loja',
      amount: amount
    },
    supportedNetworks: ['visa', 'masterCard', 'elo'], // adicione/remova redes
    merchantCapabilities: ['supports3DS'],
    requiredShippingContactFields: [] // se precisar de endereço, adicione aqui
  };

  try {
    const session = new ApplePaySession(3, paymentRequest);

    session.onvalidatemerchant = async (event) => {
      log('Validando merchant com backend...');
      try {
        // envía a validationURL para o backend, que deve solicitar a Apple e devolver o merchantSession
        const resp = await fetch(VALIDATE_ENDPOINT, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ validationURL: event.validationURL })
        });

        if (!resp.ok) throw new Error('Erro na validação (backend retornou ' + resp.status + ')');

        const merchantSession = await resp.json();
        // complete a validação com o objeto recebido do backend (merchant session)
        session.completeMerchantValidation(merchantSession);
        log('Merchant validado com sucesso.');
      } catch (err) {
        console.error(err);
        log('Falha ao validar merchant: ' + err.message, true);
        session.abort();
      }
    };

    session.onpaymentauthorized = async (event) => {
      log('Pagamento autorizado localmente — recuperando token...');
      // Token: event.payment.token (object). Conteúdo varía por versão, geralmente token.paymentData
      const paymentToken = event.payment.token; // envia isso ao backend
      // opcional: inclui metadados da compra
      const payload = {
        paymentToken,
        amount,
        currency,
        // orderId: 'MEU-ORDER-123' // adicione se quiser
      };

      try {
        // Mostramos o token no front para debug e também enviamos para o seu backend
        tokenPre.textContent = JSON.stringify(paymentToken, null, 2);
        resultBox.classList.remove('hidden');

        // enviar ao backend para processar/capturar:
        // const resp = await fetch(PROCESS_PAYMENT_ENDPOINT, {
        //   method: 'POST',
        //   headers: { 'Content-Type': 'application/json' },
        //   body: JSON.stringify(payload)
        // });

        // if (!resp.ok) {
        //   const txt = await resp.text().catch(()=>'');
        //   log('Backend retornou erro ao processar pagamento: ' + resp.status + ' ' + txt, true);
        //   // informar Apple que o pagamento falhou:
        //   session.completePayment(ApplePaySession.STATUS_FAILURE);
        //   return;
        // }

        // const data = await resp.json().catch(()=>({ ok: true }));
        // // informe a Apple que pagamento ocorreu com sucesso
        // session.completePayment(ApplePaySession.STATUS_SUCCESS);
        log('Token enviado ao backend com sucesso. Resposta backend: ' + (data.message || 'ok'));
      } catch (err) {
        console.error(err);
        session.completePayment(ApplePaySession.STATUS_FAILURE);
        log('Erro ao enviar token ao backend: ' + err.message, true);
      }
    };

    session.oncancel = (event) => {
      log('Sessão Apple Pay cancelada pelo usuário.');
    };

    // inicia
    session.begin();
  } catch (err) {
    console.error(err);
    log('Erro ao iniciar Apple Pay: ' + err.message, true);
  }
});

showDebugBtn.addEventListener('click', () => {
  resultBox.classList.toggle('hidden');
});

copyBtn.addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(tokenPre.textContent);
    alert('Token copiado para a área de transferência.');
  } catch (e) {
    alert('Falha ao copiar: ' + e.message);
  }
});

init();
</script>
</body>
</html>
