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

  if (!botToken || !chatId) {
    // Not configured: return helpful error so site owner can set env vars on Vercel
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

    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!r.ok) {
      const body = await r.text();
      console.error('Telegram API error', r.status, body);
      res.status(502).send('Failed to send notification.');
      return;
    }

    res.status(200).send('Thank You! Your booking has been sent.');
  } catch (err) {
    console.error('Booking handler error', err);
    res.status(500).send('There was a problem with your submission, please try again.');
  }
}
