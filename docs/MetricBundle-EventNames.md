# Event Name nei Grafici — AdminMetricBundle

## Tab di navigazione (riepilogo numerico per ogni periodo)

Questi valori appaiono come sommario nelle tab, **non come grafico**:

| Event Name | Funzione Twig | Cosa mostra |
|---|---|---|
| `order_total` | `metric_accumulation` | Fatturato totale del periodo (diviso 100 → da centesimi a valuta) |
| `order_nb` | `metric_beacon_total` | Numero totale di ordini del periodo |

---

## Line Chart — "Visitatori"

Event: **`pv`** (page view)

| Serie | Funzione Twig | Cosa mostra |
|---|---|---|
| Visitatori unici | `metric_beacon_unique` | Sessioni distinte per slot temporale (via HyperLogLog Redis) |
| Visitatori totali | `metric_beacon_total` | Accessi totali per slot temporale (contatore semplice) |

L'evento `pv` viene registrato ad ogni pagina visitata nel frontend dello store.

---

## Bar Chart — "Vendite"

Event: **`order_total`**

| Serie | Funzione Twig | Cosa mostra |
|---|---|---|
| Fatturato | `metric_accumulation / 100` | Somma degli importi degli ordini per slot, convertita da centesimi |

L'evento è emesso dall'`AddOrderCompletedMetricEventListener` al completamento di ogni ordine.

---

## Doughnut Chart — "Conversione carrello"

Ogni fetta corrisponde a un evento distinto, interrogato con `metric_beacon_unique`:

| Fetta (label i18n) | Event Name | Cosa rappresenta |
|---|---|---|
| Vista carrello | `pv_store_cart_view` | Utenti che hanno visualizzato il carrello |
| Indirizzo/spedizione | `pv_store_checkout_address` | Utenti arrivati allo step indirizzo/spedizione |
| Pagamento | `pv_store_checkout_payment` | Utenti arrivati allo step pagamento |
| Ordine completato | `order_nb` | Utenti che hanno effettivamente completato l'ordine |

Il grafico mostra il funnel di conversione: quanti utenti entrano nel carrello e quanti arrivano fino all'acquisto.

---

## Doughnut Chart — "Dispositivi"

| Fetta | Event Name | Cosa rappresenta |
|---|---|---|
| Mobile | `mob` | Visitatori da dispositivo mobile |
| Desktop | `no_mob` | Visitatori da desktop |

Entrambi interrogati con `metric_beacon_unique`.

---

## Doughnut Chart — "Sorgente di traffico"

| Fetta | Event Name | Cosa rappresenta |
|---|---|---|
| Diretto | `source_dir` | Accessi diretti (nessun referrer) |
| Referral | `source_ref` | Accessi da altri siti |
| Motore di ricerca | `source_se` | Accessi da search engine |

Tutti interrogati con `metric_beacon_unique`.

---

## Riepilogo di tutti gli event name

| Event Name | Tipo metrica | Dove viene emesso | Usato in |
|---|---|---|---|
| `pv` | `BEACON_UNIQUE` + `BEACON_TOTAL` | Frontend (pixel tracker) | Line chart visitatori |
| `order_total` | `ACCUMULATED` | `AddOrderCompletedMetricEventListener` | Bar chart vendite + tab riepilogo |
| `order_nb` | `BEACON_ALL` | `AddOrderCompletedMetricEventListener` | Doughnut conversione + tab riepilogo |
| `pv_store_cart_view` | `BEACON_UNIQUE` | Frontend (pixel tracker) | Doughnut conversione |
| `pv_store_checkout_address` | `BEACON_UNIQUE` | Frontend (pixel tracker) | Doughnut conversione |
| `pv_store_checkout_payment` | `BEACON_UNIQUE` | Frontend (pixel tracker) | Doughnut conversione |
| `mob` | `BEACON_UNIQUE` | Frontend (pixel tracker) | Doughnut dispositivi |
| `no_mob` | `BEACON_UNIQUE` | Frontend (pixel tracker) | Doughnut dispositivi |
| `source_dir` | `BEACON_UNIQUE` | Frontend (pixel tracker) | Doughnut sorgente traffico |
| `source_ref` | `BEACON_UNIQUE` | Frontend (pixel tracker) | Doughnut sorgente traffico |
| `source_se` | `BEACON_UNIQUE` | Frontend (pixel tracker) | Doughnut sorgente traffico |

`order_total` e `order_nb` sono gli unici event emessi lato server (PHP). Tutti gli altri provengono dal pixel tracker nel browser del cliente.
