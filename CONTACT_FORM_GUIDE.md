# Contact Form - Security & Implementation Guide

## ✅ Fixed Issues

### Previous 500 Error - SOLVED
The 500 Internal Server Error has been fixed with:
1. **Better error handling** - Errors are logged, not displayed to users
2. **Graceful fallback** - If email fails, user still gets a success message with direct email
3. **Multiple sending methods** - Tries `mail()`, then sendmail
4. **Proper JSON responses** - Always returns valid JSON

---

## 🔒 Security Features Implemented

### 1. **Input Validation**
```php
- Name: 2-100 characters, letters/spaces/hyphens/apostrophes only
- Email: Validated with FILTER_VALIDATE_EMAIL, max 255 chars
- Subject: Max 200 characters
- Message: 10-5000 characters (prevents spam & very short messages)
```

### 2. **Rate Limiting (Anti-Spam)**
```php
- 60-second cooldown between submissions
- Uses PHP sessions to track submission time
- Returns HTTP 429 (Too Many Requests) if exceeded
```

### 3. **Honeypot Fields (Bot Detection)**
```html
<!-- Hidden fields that humans can't see but bots will fill -->
<input type="text" name="website_url" style="display:none">
<input type="text" name="phone" style="display:none">
```
- If these fields are filled, the submission is silently accepted (to confuse bots)
- No email is sent, but bot thinks it succeeded

### 4. **XSS/Injection Protection**
```php
// Blocks dangerous patterns
$suspicious_patterns = [
    '/<script/i',          // Script tags
    '/javascript:/i',      // JavaScript protocol
    '/on\w+\s*=/i',        // Event handlers (onclick, onload, etc.)
    '/<iframe/i',          // IFrames
    '/<object/i',          // Objects
    '/<embed/i',           // Embeds
    '/content-type:/i',    // Header injection
    '/boundary=/i'         // MIME boundary injection
];
```

### 5. **CSRF Protection (Optional)**
```php
// Validates CSRF token if provided
if (isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Reject invalid tokens
    }
}
```

### 6. **Secure Email Headers**
```php
$headers[] = "From: Portfolio Contact <noreply@domain>";
$headers[] = "Reply-To: {$email}";
$headers[] = "X-Mailer: PHP/" . phpversion();
$headers[] = "Content-Type: text/plain; charset=UTF-8";
```

### 7. **Error Logging**
```php
- Errors are logged to server error log
- Users see friendly messages, not technical details
- Helps with debugging without exposing vulnerabilities
```

---

## 📝 How to Test the Contact Form

### Method 1: Using a Real PHP Server (Recommended)

1. **Upload to your web hosting**
   - Upload all files to your server
   - Make sure PHP is enabled
   - Test the form

2. **Use XAMPP/WAMP locally**
   ```bash
   # Start Apache server in XAMPP/WAMP
   # Place files in htdocs folder
   # Access via http://localhost/FolioOne
   ```

### Method 2: Using PHP Built-in Server with Mail Catcher

1. **Install a mail catcher (optional)**
   ```bash
   # For testing email sending locally
   composer require php-mime-mail-parser/php-mime-mail-parser
   ```

2. **Test form submission**
   ```bash
   curl -X POST http://localhost:8000/forms/contact.php \
     -d "name=Test User&email=test@example.com&subject=Test&message=This is a test message"
   ```

### Method 3: Browser Testing

1. Open `index.html` or `contact.html` in your browser
2. Fill out the contact form
3. Click "Send Message"
4. You should see:
   - ✅ Success message if email sent (or fallback message)
   - ❌ Error message if validation fails

---

## 🎯 Expected Responses

### Success Response (200 OK)
```json
{
  "success": true,
  "message": "Thank you! Your message has been sent successfully. I will get back to you soon."
}
```

### Fallback Response (200 OK)
```json
{
  "success": true,
  "message": "Thank you for your message! Due to server configuration, the email could not be sent automatically. Please contact me directly at rotichbravin13@gmail.com",
  "fallback": true,
  "direct_email": "rotichbravin13@gmail.com"
}
```

### Validation Error (400 Bad Request)
```json
{
  "success": false,
  "message": "Name is required, Email is required"
}
```

### Rate Limit Error (429 Too Many Requests)
```json
{
  "success": false,
  "message": "Please wait 45 seconds before submitting again."
}
```

### Method Not Allowed (405 Method Not Allowed)
```json
{
  "success": false,
  "message": "Method not allowed. Please use POST."
}
```

---

## 🔧 Troubleshooting

### Issue: Still Getting 500 Error

**Solution 1: Check PHP Error Log**
```bash
# Common locations for PHP error logs
/var/log/php/error.log
/var/log/apache2/error.log
/var/log/nginx/error.log
```

**Solution 2: Enable Error Display Temporarily**
```php
// Add to top of contact.php (for testing only!)
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

**Solution 3: Check PHP Configuration**
```bash
# Check if mail() function is enabled
php -m | grep mail

# Check PHP version
php -v
```

### Issue: Email Not Being Received

**Solution 1: Check Spam Folder**
- Emails might be marked as spam

**Solution 2: Configure SMTP (Advanced)**
```php
// Uncomment and configure in contact.php
$contact->smtp = array(
  'host' => 'smtp.gmail.com',
  'username' => 'your-email@gmail.com',
  'password' => 'your-app-password',
  'port' => 587
);
```

**Solution 3: Use Formspree (No Backend Required)**
```html
<!-- Replace form action in HTML -->
<form action="https://formspree.io/f/YOUR_FORM_ID" method="POST">
```

### Issue: Rate Limiting Too Strict

**Solution: Adjust Rate Limit Time**
```php
// In contact.php, change this value
$rate_limit_time = 30; // Change from 60 to 30 seconds
```

---

## 📊 Security Checklist

- [x] Input validation (name, email, message length)
- [x] XSS protection (pattern detection)
- [x] SQL injection protection (using prepared statements - not applicable, no DB)
- [x] Rate limiting (60 seconds between submissions)
- [x] Honeypot fields (bot detection)
- [x] CSRF token validation (optional)
- [x] Secure email headers
- [x] Error logging (not displaying to users)
- [x] Method validation (POST only)
- [x] Character encoding (UTF-8)

---

## 🚀 Deployment Checklist

Before deploying to production:

- [ ] Test form submission on staging server
- [ ] Verify emails are being received
- [ ] Check spam folder for test emails
- [ ] Test rate limiting works
- [ ] Test validation errors display correctly
- [ ] Remove any debug code
- [ ] Set up error logging
- [ ] Configure SMTP if needed
- [ ] Test on mobile devices
- [ ] Test with different browsers

---

## 📧 Alternative: Formspree Integration

If you don't want to handle email sending yourself:

### Step 1: Sign Up for Formspree
1. Go to https://formspree.io
2. Create a free account
3. Create a new form
4. Get your form ID

### Step 2: Update HTML
```html
<!-- Replace in index.html and contact.html -->
<form 
  action="https://formspree.io/f/YOUR_FORM_ID" 
  method="POST"
  class="php-email-form"
>
  <!-- Keep existing form fields -->
</form>
```

### Step 3: Remove PHP Handler
No need for `forms/contact.php` anymore!

### Benefits of Formspree
- ✅ No backend required
- ✅ Built-in spam protection
- ✅ Email notifications
- ✅ Form submissions dashboard
- ✅ Free tier available

---

## 📞 Support

If you encounter issues:

1. Check this documentation first
2. Review PHP error logs
3. Test with a simple PHP mail script
4. Consider using Formspree as alternative

**Contact:** rotichbravin13@gmail.com

---

**Last Updated:** March 20, 2026
**Author:** Bravin Rotich
