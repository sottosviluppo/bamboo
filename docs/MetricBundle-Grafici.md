# Generazione dei Grafici delle Metriche — AdminMetricBundle

## Indice

1. [Panoramica Architetturale](#1-panoramica-architetturale)
2. [Raccolta dei Dati](#2-raccolta-dei-dati)
3. [Storage Dati](#3-storage-dati)
4. [Dashboard e Intervalli Temporali](#4-dashboard-e-intervalli-temporali)
5. [Rendering dei Grafici](#5-rendering-dei-grafici)
6. [Tipi di Grafico](#6-tipi-di-grafico)
7. [Flusso Completo End-to-End](#7-flusso-completo-end-to-end)

---

## 1. Panoramica Architetturale

Il sistema delle metriche è suddiviso in tre strati principali:

```
[Browser / Store] → [Input Layer] → [Storage Layer] → [Admin Dashboard]
                         ↓                  ↓
                    MetricManager      Redis + MySQL
```

| Strato | Bundle / Componente | Responsabilità |
|---|---|---|
| **Input** | `Component/Metric/Input` | Riceve eventi di tracking via HTTP |
| **Core** | `Component/Metric/Core` | Gestisce storage e query |
| **Store** | `Store/MetricBundle` | Emette eventi al completamento ordini |
| **Admin** | `Admin/MetricBundle` | Visualizza grafici nel pannello |

---

## 2. Raccolta dei Dati

### 2.1 Tracking via Pixel (Browser)

Il tracking avviene attraverso un pixel trasparente 1×1 caricato dal browser del cliente. Il controller che riceve questi eventi è:

**`Component/Metric/Input/Controller/InputController.php`**

```
GET /{token}/{event}.pixel?i={value}&t={type}
```

| Parametro | Descrizione | Default |
|---|---|---|
| `token` | Identificatore dello store | — |
| `event` | Nome dell'evento (es. `pv`, `order_nb`) | — |
| `i` | Valore numerico della metrica | `0` |
| `t` | Tipo di metrica (bitmask) | `TYPE_BEACON_ALL` |

Il controller risponde con un GIF trasparente base64 e registra l'evento chiamando `MetricManager::addEntry()`.

### 2.2 Tracking via Event Listener (Ordini)

Quando un ordine viene completato, l'evento `order.oncreated` viene catturato da:

**`Store/MetricBundle/EventListener/AddOrderCompletedMetricEventListener.php`**

Questo listener registra due metriche per ogni ordine:

- `order_nb` — conteggio del numero di ordini (`TYPE_BEACON_ALL`)
- `order_total` — importo totale in centesimi (`TYPE_ACCUMULATED`)

### 2.3 Tipi di Metrica (`ElcodiMetricTypes`)

```
TYPE_BEACON_UNIQUE  = 1   → conteggio visitatori unici (HyperLogLog su Redis)
TYPE_BEACON_TOTAL   = 2   → conteggio totale accessi (incremento semplice)
TYPE_BEACON_ALL     = 3   → entrambi (bitmask 1 | 2)
TYPE_ACCUMULATED    = 4   → somma di valori numerici (es. fatturato)
TYPE_DISTRIBUTIVE   = 8   → distribuzione su categorie (hash map)
```

---

## 3. Storage Dati

I dati vengono scritti in due posti in modo sincrono:

### 3.1 Database (MySQL via Doctrine ORM)

Entità: **`Component/Metric/Core/Entity/Entry.php`**

| Campo | Tipo | Descrizione |
|---|---|---|
| `id` | int | PK |
| `token` | string | Identificatore store |
| `event` | string | Nome evento |
| `value` | string | ID univoco o valore numerico |
| `type` | int | Bitmask del tipo di metrica |
| `createdAt` | DateTime | Timestamp dell'evento |

Il database funge da **sorgente di verità permanente**. Le metriche possono essere ricaricate in Redis in qualsiasi momento tramite il comando:

```bash
php app/console elcodi:metrics:load {giorni}
```

### 3.2 Redis (Cache di Query)

**`Component/Metric/Core/Bucket/RedisMetricsBucket.php`**

Redis è il layer di **lettura veloce** per la dashboard. Le chiavi sono strutturate così:

```
{token}.{event}.{Y-m-d}          → granularità giornaliera
{token}.{event}.{Y-m-d-H}        → granularità oraria
```

Per ogni evento vengono mantenuti più suffissi:

| Suffisso Redis | Struttura dati | Tipo metrica |
|---|---|---|
| _(nessuno)_ | HyperLogLog (`pfAdd/pfCount`) | `TYPE_BEACON_UNIQUE` |
| `_total` | Integer (`incr`) | `TYPE_BEACON_TOTAL` |
| `_accum` | Integer (`incrby`) | `TYPE_ACCUMULATED` |
| `_distr` | Hash Map (`hincrby/hgetall`) | `TYPE_DISTRIBUTIVE` |

---

## 4. Dashboard e Intervalli Temporali

### 4.1 Controller e Rotte

**`Admin/MetricBundle/Controller/ReportsController.php`**

| Rotta | URL | Periodo |
|---|---|---|
| `admin_reports_today` | `/reports/today` | Oggi |
| `admin_reports_yesterday` | `/reports/yesterday` | Ieri |
| `admin_reports_last_week` | `/reports/last/week` | Ultima settimana |
| `admin_reports_last_month` | `/reports/last/month` | Ultimo mese |
| `admin_reports_last_quarter` | `/reports/last/quarter` | Ultimo trimestre |

Ogni rotta renderizza `AdminMetricBundle:Reports:view.html.twig`, che a sua volta chiama dinamicamente il metodo del `PanelController` corrispondente (es. `metricPanelToday`).

### 4.2 Costruzione degli Intervalli

**`Admin/MetricBundle/Services/MetricIntervalsResolver.php`**

Il resolver costruisce un oggetto `IntervalContainer` configurato in base al tipo di periodo selezionato:

| Tipo (`AdminPanelTypes`) | Granularità | Iterazioni | Raggruppamento | Formato Legenda |
|---|---|---|---|---|
| `PANEL_TYPE_TODAY` | Oraria (`PT1H`) | 23 | 1 | `ha` (es. "3pm") |
| `PANEL_TYPE_YESTERDAY` | Oraria (`PT1H`) | 23 | 1 | `ha` |
| `PANEL_TYPE_LAST_WEEK` | Oraria (`PT1H`) | 167 | 3 | Personalizzato |
| `PANEL_TYPE_LAST_MONTH` | Giornaliera (`P1D`) | 29 | 1 | `d/m` |
| `PANEL_TYPE_LAST_QUARTER` | Giornaliera (`P1D`) | 89 | 1 | `d/m` |

### 4.3 Modelli di Intervallo

**`IntervalContainer`** — descrive l'intero range temporale:
- `startDay` — data di inizio
- `iterations` — quante slot temporali creare
- `elementsGrouping` — quante slot raggruppare in un `PartialInterval`
- `elementsFormat` — formato per le chiavi Redis (es. `Y-m-d-H`)
- `chartElementsSeparation` — ogni quanti elementi mostrare una label sull'asse X
- `partialIntervals` — array di `PartialInterval`
- `elements` — tutte le date formattate (passate al bucket Redis)

**`PartialInterval`** — un gruppo di slot raggruppate:
- `elements` — array di date (chiavi Redis da interrogare come aggregato)
- `first` — `DateTime` del primo elemento (usato per la label sull'asse X)

---

## 5. Rendering dei Grafici

### 5.1 Estensioni Twig

Sono disponibili due estensioni Twig che collegano i template con Redis:

**`APIMetricExtension`** (Component):

| Funzione Twig | Metodo Redis | Tipo dati restituito |
|---|---|---|
| `metric_beacon_unique(token, event, dates)` | `pfCount` su HyperLogLog | Intero |
| `metric_beacon_total(token, event, dates)` | Somma contatori `_total` | Intero |
| `metric_accumulation(token, event, dates)` | Somma valori `_accum` | Intero |
| `metric_distributions(token, event, dates)` | Hash map `_distr` | Array associativo |

**`MetricIntervalsExtension`** (Admin):

| Funzione Twig | Descrizione |
|---|---|
| `metric_create_interval_container(type)` | Crea l'`IntervalContainer` per il periodo selezionato |

### 5.2 Serializzazione verso JavaScript

I template Twig non passano i dati direttamente a JavaScript. Il pattern usato è:

1. Il template crea un elemento `<canvas>` con attributi `data-*`
2. I valori numerici vengono scritti in `<input type="hidden">` dentro o accanto al canvas
3. La libreria JavaScript (`data-fc-modules="charts"`) legge quegli input e costruisce il grafico

Esempio da `metricLine.html.twig`:

```html
<canvas
  data-fc-type="line"
  data-fc-modules="charts"
  data-fc-labels-x="{{ partialInterval.first | date(chartLegendFormat) }}"
  data-fc-text-no-data="Nessun dato disponibile">
</canvas>

{% for partialInterval in intervalContainer.partialIntervals %}
  <input type="hidden"
    name="metric_beacon_unique"
    value="{{ metric_beacon_unique(tracker, 'pv', partialInterval.elements) }}" />
  <input type="hidden"
    name="metric_beacon_total"
    value="{{ metric_beacon_total(tracker, 'pv', partialInterval.elements) }}" />
{% endfor %}
```

---

## 6. Tipi di Grafico

### 6.1 Line Chart — `metricLine.html.twig`

**Dati**: Visitatori unici e totali nel tempo
**Attributo canvas**: `data-fc-type="line"`
**Sorgenti**:
- `metric_beacon_unique` — visitatori unici per slot
- `metric_beacon_total` — accessi totali per slot

Itera su `intervalContainer.partialIntervals` e mostra un punto ogni `chartElementsSeparation` slot.

---

### 6.2 Bar Chart — `metricOrderTotals.html.twig`

**Dati**: Fatturato per periodo (in unità di valuta)
**Attributo canvas**: `data-fc-type="bar"`
**Sorgente**:
- `metric_accumulation(tracker, 'order_total', partialInterval.elements) / 100`

Il valore viene diviso per 100 perché gli importi sono salvati in centesimi.

---

### 6.3 Doughnut Chart — `metricCheese.html.twig`

**Dati**: Percentuali di conversione, dispositivi, sorgenti di traffico
**Attributo canvas**: `data-fc-type="doughnut"`
**Colori fissi**: `[b55151, 5b90bf, b48ead, 96c47f, 82b2af]`

Legge una mappa di eventi e li aggrega per costruire le fette del grafico. Esempi di segmenti tipici:
- Tasso di conversione: viste carrello → pagamenti completati
- Dispositivi: mobile vs desktop
- Sorgenti: diretto, referral, motori di ricerca

---

### 6.4 Tabella Top — `metricTop.html.twig`

**Dati**: Top 10 distribuzioni per un evento
**Sorgente**: `metric_distributions(tracker, event, intervalContainer.elements) | slice(1, 10)`

Usa `TYPE_DISTRIBUTIVE` (hash map Redis) e mostra le 10 voci più frequenti in una tabella HTML.

---

## 7. Flusso Completo End-to-End

```
ACQUISIZIONE DATI
─────────────────
Browser cliente
    │  GET /{token}/{event}.pixel?i={val}&t={type}
    ▼
InputController::addEntryAction()
    │
    ▼
MetricManager::addEntry()
    ├── Persiste Entry su MySQL (via Doctrine)
    └── RedisMetricsBucket::add()
            ├── pfAdd  → chiave HyperLogLog  (unique)
            ├── incr   → chiave _total        (total)
            ├── incrby → chiave _accum        (accumulation)
            └── hincrby→ chiave _distr        (distribution)

Evento ordine completato
    │  order.oncreated
    ▼
AddOrderCompletedMetricEventListener::addMetric()
    └── MetricManager::addEntry() ×2  (order_nb + order_total)


VISUALIZZAZIONE DASHBOARD
──────────────────────────
Admin seleziona periodo (es. "Ultima settimana")
    │  GET /reports/last/week
    ▼
ReportsController::viewAction("metricPanelLastWeek")
    │  render AdminMetricBundle:Reports:view.html.twig
    ▼
Sub-request → PanelController::metricPanelLastWeekAction()
    │  render AdminMetricBundle:Panel:panel.html.twig
    │  con type = PANEL_TYPE_LAST_WEEK
    ▼
Template: metric_create_interval_container(type)
    │
    ▼
MetricIntervalsResolver::getIntervalContainer(PANEL_TYPE_LAST_WEEK)
    │  Crea IntervalContainer:
    │    - granularità: PT1H
    │    - iterazioni: 167
    │    - raggruppamento: 3
    │    - 56 PartialIntervals (167 / 3 ≈ 56)
    ▼
Template itera partialIntervals
    │  Per ogni PartialInterval:
    │    metric_beacon_unique(token, event, partialInterval.elements)
    │         → RedisMetricsBucket::getBeaconsUnique()
    │         → pfCount su chiavi Redis
    │    metric_accumulation(token, 'order_total', ...)
    │         → RedisMetricsBucket::getAccumulation()
    │         → somma valori _accum
    ▼
Valori scritti in <input type="hidden"> accanto a <canvas>
    ▼
JavaScript (data-fc-modules="charts")
    │  Legge tutti gli input hidden
    │  Costruisce dataset
    └── Renderizza grafico (line / bar / doughnut)
```

---

## File di Riferimento

| File | Responsabilità |
|---|---|
| [AdminPanelTypes.php](src/Elcodi/Admin/MetricBundle/AdminPanelTypes.php) | Costanti dei tipi di periodo |
| [PanelController.php](src/Elcodi/Admin/MetricBundle/Controller/PanelController.php) | Controller pannello metriche |
| [ReportsController.php](src/Elcodi/Admin/MetricBundle/Controller/ReportsController.php) | Controller reports |
| [MetricIntervalsResolver.php](src/Elcodi/Admin/MetricBundle/Services/MetricIntervalsResolver.php) | Costruisce gli intervalli temporali |
| [MetricIntervalsExtension.php](src/Elcodi/Admin/MetricBundle/Twig/MetricIntervalsExtension.php) | Funzione Twig `metric_create_interval_container` |
| [IntervalContainer.php](src/Elcodi/Admin/MetricBundle/Model/IntervalContainer.php) | Modello del contenitore intervalli |
| [PartialInterval.php](src/Elcodi/Admin/MetricBundle/Model/PartialInterval.php) | Modello singolo intervallo raggruppato |
| [panel.html.twig](src/Elcodi/Admin/MetricBundle/Resources/views/Panel/panel.html.twig) | Template principale dashboard |
| [metricLine.html.twig](src/Elcodi/Admin/MetricBundle/Resources/views/Metric/metricLine.html.twig) | Template line chart |
| [metricOrderTotals.html.twig](src/Elcodi/Admin/MetricBundle/Resources/views/Metric/metricOrderTotals.html.twig) | Template bar chart fatturato |
| [metricCheese.html.twig](src/Elcodi/Admin/MetricBundle/Resources/views/Metric/metricCheese.html.twig) | Template doughnut chart |
| [metricTop.html.twig](src/Elcodi/Admin/MetricBundle/Resources/views/Metric/metricTop.html.twig) | Template tabella top 10 |
| [AddOrderCompletedMetricEventListener.php](src/Elcodi/Store/MetricBundle/EventListener/AddOrderCompletedMetricEventListener.php) | Registra metriche ordini |
