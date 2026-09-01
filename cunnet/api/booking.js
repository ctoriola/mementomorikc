export default async function handler(req, res) {
  if (req.method !== 'POST') {
    res.status(405).send('Method Not Allowed');
    return;
  }

  const { name, email, phone, date, time, message } = req.body || {};

  if (!name || !phone || !email) {
    res.status(400).send('Please complete the form and try again.');
    return;
  }

  const lines = [];
  lines.push('*New booking request*');
  lines.push(`Name: ${name}`);
  lines.push(`Email: ${email}`);
  lines.push(`Phone: ${phone}`);
  if (date) lines.push(`Date: ${date}`);
  if (time) lines.push(`Time: ${time}`);
  if (message) lines.push(`Message: ${message}`);

  const text = lines.join('\n');

  const botToken = process.env.TELEGRAM_BOT_TOKEN;
  const chatId = process.env.TELEGRAM_CHAT_ID; // your personal chat id

  console.log('Booking handler called');
  console.log('Bot Token present:', !!botToken);
  console.log('Chat ID present:', !!chatId);
  console.log('Chat ID value:', chatId);

  if (!botToken || !chatId) {
    // Not configured: return helpful error so site owner can set env vars on Vercel
    console.error('Missing Telegram configuration:', { botToken: !!botToken, chatId: !!chatId });
    res.status(500).send('Server not configured for Telegram notifications.');
    return;
  }

  try {
    const url = `https://api.telegram.org/bot${botToken}/sendMessage`;
    const payload = {
      chat_id: chatId,
      text,
      parse_mode: 'Markdown'
    };

    console.log('Sending to Telegram URL:', url);
    console.log('Payload:', JSON.stringify(payload, null, 2));

    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    console.log('Telegram response status:', r.status);

    if (!r.ok) {
      const body = await r.text();
      console.error('Telegram API error', r.status, body);
      res.status(502).send('Failed to send notification.');
      return;
    }

    const responseData = await r.json();
    console.log('Telegram response success:', responseData);
    res.status(200).send('Thank You! Your booking has been sent.');
  } catch (err) {
    console.error('Booking handler error', err);
    res.status(500).send('There was a problem with your submission, please try again.');
  }
}
