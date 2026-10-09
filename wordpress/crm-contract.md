# Belmains CRM v0.1 — implementation contract

Plugin: wordpress/belmains-crm/, vanilla PHP 8 / WordPress admin / JS CSS without build.
Main constants BCRM_VERSION, BCRM_PATH, BCRM_URL. Admin page slug belmains-crm. Capability manage_woocommerce OR manage_options; all private REST endpoints use WordPress cookie auth + nonce. WooCommerce CRUD, HPOS compatible. No demo data in real metrics.

JS boot global `BelmainsCRM`: {api: full REST root `belmains-crm/v1/`, nonce, currency:'EUR', siteName, wooUrl, settingsUrl, ajaxUrl}. Root element `#bcrm-app`.
All dates YYYY-MM-DD in WP timezone; date range inclusive, maximum 366 days. Default last 30 days. REST errors {code,message}.

GET dashboard?from&to returns {commerce: summary, audience: audience, connections:{woocommerce:bool,iziship_last_sync:string|null,site_public_https:bool}, generated_at, from,to}.
summary: {available:bool, currency, metrics:{orders_total,orders_paid,orders_pending,orders_failed,orders_cancelled,orders_refunded,net_revenue,refund_total,average_order,units_sold,customers,returning_customers,profit:null|number,profit_coverage:number}, daily:[{date,orders,revenue}], statuses:[{key,label,count}], products:[{id,name,quantity,revenue}], warnings:[string]}.
Revenue = paid/settled orders created in selected period, totals excluding tax and net of refunds; labelled cohort by order creation date. Currency not mixed. Profit null if required costs incomplete; otherwise excludes optional marketing costs, explicitly labelled contribution margin. Return metrics count repeat buyers within period (not lifetime).
audience: {enabled:bool,visitors,sessions,pageviews,product_views,add_to_cart,checkout,tracked_orders,conversion:null|number,daily:[{date,visitors,pageviews}],sources:[{source,visitors}],devices:[{device,visitors}],coverage_note}. Consent first, pseudonymous local analytics; admin visits excluded. No identifiable visitor history.

GET orders?from&to&page&search&status returns {items:[{id,number,date,customer,email,status,status_label,total,currency,items_count,edit_url,tracking:{number,carrier,url,status,status_label,shipped_at,delivered_at,source}}],total,pages,page,warnings:[]}.
GET orders/{id} returns same item plus {items:[{name,quantity,total}],shipping_address,billing_phone,notes:[{date,content}],costs:{goods:null|number,shipping:null|number,packaging:null|number,fees:null|number},customer_note}.
POST orders/{id}/costs body {goods,shipping,packaging,fees}, blank=>null. Values nonnegative. POST orders/{id}/note body {note}, internal only; no emails. Returns {success:true}.
GET customers?from&to&page&search returns {items:[{key,name,email,phone,orders,spent,first_order,last_order,segment}],total,pages,page,warnings:[]}. Includes guest buyers, period totals, no fake lifetime value. UI click email filters orders rather than exposing public records.
GET stock?page&search returns {items:[{id,name,sku,quantity:null|number,status,manage_stock,price,currency,edit_url}],total,pages,page}. Write stock through existing Woo product edit link, not a new inventory authority.

GET shipments?from&to&page&search&status returns {items: order items above, metrics:{awaiting,shipped,in_transit,relay,delivered,exception,returned},total,pages,page,warnings:[]}.
POST orders/{id}/tracking body {number,carrier,url,status,shipped_at,delivered_at}; statuses: pending,shipped,in_transit,relay,delivered,exception,returned. Validate HTTPS tracking URL; do NOT infer delivered from Woo completed. Returns {success:true}.
GET integration returns {method:'tracking_number',last_sync:null|string,external_updates:number,site_public_https:bool,guide_url,rest_keys_url,fields:[{name,description}],warnings:[]}.
No Iziship credential is created/transmitted automatically. Standard Iziship Woo REST metadata writes are observed; tracking_number primary, carrier optional. API keys managed via Woo settings. Real connection requires HTTPS publicly reachable site and sending dedicated keys to Iziship via agreed secure channel; don't claim connected until observed external updates.

GET settings returns {analytics_enabled:bool,retention_days:90,low_stock_threshold:5,shipping_sla_days:3}.
POST settings body same fields (retention 30..365, low_stock 1..100, sla 1..30). Admin permissions, nonce.
GET tickets?status returns {items:[{id,subject,customer_email,order_id,status,priority,message,created_at,updated_at}],counts:{open,pending,closed}}.
POST tickets body {subject,customer_email,order_id,status,priority,message}. POST tickets/{id} body {status,priority,note}. Internal tickets only, no automatic emails. UI creates/updates with clear confirmation.
GET export?type=orders|customers&from&to returns {filename,csv}; authenticated, formula escaped. UI blob download. No public CSV endpoint.

UI sections: Overview, Commandes, Clients, Expéditions, Stocks, SAV, Connexions. Settings/analytics within Connexions. France date/money formatting, accessible empty/error/loading states, period selector, search, real detail drawer for orders + costs/notes/tracking, exports. No mock data, no inflated metrics, no unsupported auto marketing promises. No external dependencies, remote fonts or assets.
