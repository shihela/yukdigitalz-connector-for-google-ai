=== YukDigitalz Connector for Google AI ===
Contributors: shihela
Tags: gemini, google ai, ai connector, gemini 3.7, failover
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Google AI Studio & Gemini API connector for WordPress with dynamic model switching, 503/429 auto-failover, multi-key rotation, and usage telemetry.


== Description ==

Solves the primary challenges faced by Google Gemini API users on WordPress: **Error 503 (Service Unavailable - Capacity / High Demand)** and **Error 429 (Rate Limit / Quota Exceeded)**.

As a **pure, non-intrusive provider connector & failover engine**, **YukDigitalz Connector for Google AI** extends Google AI Studio and Gemini models into WordPress and integrates seamlessly with the **WordPress Core AI Client framework** (`wp_ai_providers`, `wp_ai_client_generate`, `wp_ai_client_default_model`). It works alongside WordPress Connectors and 3rd-party BYOK AI plugins without taking over database options, altering prompts, or blocking API keys. When Google AI Studio experiences server capacity issues or rate limit limits, the engine intelligently performs retries with exponential backoff, rotates secondary API keys, and seamlessly routes requests to designated fallback models (such as Gemini 3.7 Flash, Gemini 2.5 Flash, Gemini 2.0 Flash, Gemini 1.5 Flash, Gemini 1.5 Pro, or custom fine-tuned model IDs).

### Key Features:
* **Pure Non-Intrusive Companion Architecture**: Zero option hijacking, zero prompt manipulation, and 100% raw payload pass-through for multi-turn chats and system instructions.
* **WordPress Core AI Client & Connectors Integration**: Built-in support for WordPress Core AI Client (`wp_ai_providers`, `wp_ai_client_generate`) to register Google Gemini as an active provider for core and third-party plugins.
* **Dynamic Model Switcher & Live Fetcher**: Select active primary models or pull the latest models directly from Google AI Studio API with a single click.
* **Dual-Layer Auto-Failover Engine**: Protects against 503/429/500/timeout errors by pairing multi-key rotation pools with official Google Gemini fallback model hierarchies.
* **Circuit Breaker Transient Cooldown**: Prevents repeated request stalls by placing capacity-constrained models (503) on temporary transient cooldowns.
* **API Key Rotation Pool**: Automatically rotates through secondary Google AI Studio API keys when rate limit 429 errors occur.
* **Official Model Support**: Native support for Gemini 3.7 Flash, Gemini 2.5 Flash, Gemini 2.5 Pro, Gemini 2.0 Flash, Gemini 1.5 Flash, Gemini 1.5 Pro, Gemini 2.0 Flash-Lite, and custom fine-tuned model IDs.
* **Enterprise Security & Key Fallbacks**: Secure API key storage using OpenSSL AES-256-CBC encryption, with support for `wp-config.php` constants (`YUKDICONFO_API_KEY`, `GOOGLE_API_KEY`) and passive WordPress Connectors key resolution.
* **Universal Network Interceptor**: Intercepts outgoing Google AI API generation calls made by third-party plugins without interfering with API key saving or GET validation calls.
* **Developer & REST API**: Global function `yukdiconfo_generate()`, filter hooks, and REST API endpoint (`/wp-json/yukdiconfo/v1/generate`).
* **Telemetry & Request Audit Logs**: Monitor performant request metrics, local site timestamps, status codes, latency, token usage, and filterable audit logs.

== External Services ==

This plugin relies on third-party external services to process artificial intelligence (AI) models and text generation. Details of the external service used by this plugin are provided below:

* **Service Name**: Google AI Studio & Gemini API (provided by Google LLC)
* **What the Service Does**: Used for fetching available Google Gemini AI models, testing API key credential connectivity, and processing artificial intelligence (AI) text generation requests.
* **Data Transmitted**: API Key credentials provided by the website admin, user-provided text prompts, system instructions, and selected model generation parameters (e.g. temperature, top_p, max_tokens).
* **When Data is Transmitted**: Data is transmitted to Google AI servers ONLY when an admin, user, or active WordPress plugin explicitly triggers an AI text generation request, performs an API key connectivity test, or clicks the button to fetch available Gemini models.
* **Terms of Service**: https://ai.google.dev/terms and https://policies.google.com/terms
* **Privacy Policy**: https://policies.google.com/privacy

== Installation ==

1. Upload the `yukdigitalz-connector-for-google-ai` plugin folder to the `/wp-content/plugins/` directory, or install via the WordPress Plugins menu.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Open the **YukDigitalz Connector AI** menu in the admin sidebar.
4. Enter your Gemini API Key under the **Gemini API & Models** tab (or define `YUKDICONFO_API_KEY` in `wp-config.php`, or use the official Connectors plugin).
5. Select your Primary Model and configure Fallback Models under the **Auto-Failover & Cooldown** tab.

== Frequently Asked Questions ==

= How does this connector plugin interact with WordPress Connectors and BYOK plugins? =
It operates as a pure companion connector. It never overwrites database options of other plugins or modifies prompt contents. It only swaps model IDs on outgoing cURL calls and handles failover retries seamlessly.

= How do I use YukDigitalz Connector for Google AI in my theme or another plugin? =
Simply call the global function `yukdiconfo_generate( 'Your prompt here' )`. The engine will automatically manage model selection and failover.

= Is it safe to store API Keys in the database? =
Yes, highly secure. All API keys are encrypted using AES-256 standards with WordPress unique salt keys. You can also define them in `wp-config.php` for enterprise-grade security.

== Changelog ==

= 1.0.0 =
* Initial official release of YukDigitalz Connector for Google AI for WordPress.
* Includes Intelligent 503/429 Auto-Failover, Multi-Key Rotation Pool, Circuit Breaker Transient Cooldown, Dynamic Model Fetcher, Pure Non-Intrusive Network Interceptor, Raw Payload Pass-Through, and Telemetry Audit Logs.

