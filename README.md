# FOSSBilling KeyHelp Server Manager

A production-ready **KeyHelp server manager module for FOSSBilling** that enables automatic provisioning and management of hosting accounts using the KeyHelp API.

This module allows hosting providers to automate account creation, domain provisioning, and service lifecycle management directly from FOSSBilling.

---

## 🚀 Features

* Automatic hosting account provisioning
* Automatic domain creation via KeyHelp API
* Multi-domain support for existing users
* Domain format validation
* Hosting plan domain limit enforcement
* Prevent duplicate domains
* Automatic domain removal when service is cancelled
* Smart API caching
* Exponential backoff to avoid API rate limits
* Secure password generation
* JSON error-safe API handling
* Detailed logging for troubleshooting

---

## ⚙️ Supported Operations

| Operation           | Supported |
| ------------------- | --------- |
| Create Account      | ✅         |
| Add Domain          | ✅         |
| Suspend Account     | ✅         |
| Unsuspend Account   | ✅         |
| Change Password     | ✅         |
| Change Hosting Plan | ✅         |
| Cancel Service      | ✅         |
| Remove Domain       | ✅         |
| Synchronize Domain  | ✅         |

---

## 🧱 Architecture

The module is built with stability and performance in mind.

Key design principles:

* Minimal API calls
* Smart caching
* Safe JSON parsing
* Clear error handling
* Secure password generation
* Domain validation

---

## 📦 Requirements

* PHP 8+
* FOSSBilling
* KeyHelp Server
* KeyHelp API enabled

---

## 📥 Installation

1. Copy the module file into:

```
/library/Server/Manager/
```

2. Login to **FOSSBilling Admin Panel**

3. Navigate to:

```
System → Servers
```

4. Add a new server.

5. Select:

```
KeyHelp
```

6. Enter:

* KeyHelp Hostname
* KeyHelp API Key

7. Save the configuration.

---

## 🔧 Configuration

The module requires a KeyHelp API key.

Generate it inside KeyHelp:

```
KeyHelp → Settings → API
```

Copy the API key and paste it into the FOSSBilling server configuration.

---

## 🛡 Security

This module includes multiple safety mechanisms:

* Domain format validation
* Prevention of duplicate domains
* Hosting plan domain limits enforcement
* Safe JSON decoding
* API retry logic
* Controlled logging

---

## 📊 Logging

Logs are written using PHP error logging.

Typical log examples:

```
KeyHelp: Account created user123 for example.com
KeyHelp: Domain deleted example.com
KeyHelp: JSON decode failed
```

---

## 🧪 Tested With

* FOSSBilling
* KeyHelp
* PHP 8.x

---

## 👨‍💻 Author

**Mostafa Mohamed**

Founder of **Faster Services**

GitHub
https://github.com/Mostafa96-cybersecurity

---

## 📜 License

MIT License

---

## 💬 Support

If you encounter any issues or have feature requests, please open a discussion or issue in this repository.
