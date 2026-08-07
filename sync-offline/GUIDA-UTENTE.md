# Aggiornare il menù del sito — Guida rapida

Questa guida spiega come **aggiornare il menù del sito** prendendo i piatti
direttamente dal **gestionale della cassa**.

Non serve sapere niente di informatica: sono due clic.

---

## Prima di iniziare

Servono due cose:

1. Il **gestionale acceso** (il programma della cassa).
2. Il PC che stai usando **collegato alla stessa rete** del gestionale
   (stesso wi-fi o stesso cavo di rete).

Se il gestionale è spento, il programma non trova i piatti e dà errore.

---

## Come si aggiorna il menù

**1.** Fai doppio clic su **`sync.exe`**.

Si apre una finestra con tre caselle, già compilate con i valori dell'ultima
volta. Normalmente **non devi toccare niente**.

**2.** Premi il pulsante **“Sincronizza e carica”**.

**3.** Nel riquadro bianco in basso scorrono le righe di avanzamento.
Aspetta qualche secondo: alla fine deve comparire

```
Risposta: {"ok":true, ... }

FATTO.
```

La parola **`"ok":true`** e la scritta **`FATTO.`** vogliono dire che è andato
tutto bene. **Il menù del sito è aggiornato.** Puoi chiudere la finestra.

> Se invece compare la parola **`ERRORE`**, guarda la tabella in fondo
> a questa guida.

---

## Le tre caselle, se ti capita di doverle ricompilare

Succede solo se il programma viene reinstallato o se le caselle sono vuote.

| Casella | Cosa va scritto |
|---|---|
| **Gestionale (LAN)** | L'indirizzo del gestionale. `http://localhost:9020` se sei seduto al PC della cassa; se sei su un altro PC, l'indirizzo di quello della cassa, per esempio `http://192.168.1.10:9020` |
| **URL upload (sito)** | `https://www.sagralozzo.it/test/api/offline-data.php` |
| **API key** | La chiave che trovi nel pannello `settings/` del sito |

Quello che scrivi viene **salvato in automatico**: la volta dopo lo ritrovi
già pronto.

---

## Il secondo pulsante: “Genera solo offline-data.json”

Questo pulsante legge i piatti dal gestionale e crea il file
**senza pubblicarlo sul sito**.

Serve per **provare** che il gestionale risponda, senza toccare il sito che
vedono i clienti.

- Basta che sia compilata la casella **Gestionale (LAN)**; le altre due non
  vengono usate.
- Alla fine compare la riga `Nessun upload: file generato solo in locale.`
- **Il sito resta com'era.** Cambia solo quando premi “Sincronizza e carica”.

---

## Come controllare che il sito sia davvero aggiornato

Apri il sito e guarda il menù.

Se vuoi la controprova, apri nel browser questo indirizzo:

```
https://www.sagralozzo.it/test/api/offline-data.php
```

Compare una pagina piena di scritte e parentesi: è normale, è il menù nel
formato che usa il sito. Cerca il nome di un piatto che hai appena cambiato —
se c'è, l'aggiornamento è arrivato.

---

## Se qualcosa non va

| Cosa vedi | Perché | Cosa fare |
|---|---|---|
| `ERRORE` con scritto che non riesce a collegarsi | Il gestionale è spento, oppure il PC non è sulla stessa rete | Accendi il gestionale, controlla la rete, riprova |
| Risposta con `"error"` e il numero **401** | La API key è sbagliata | Ricopia la chiave esatta dal pannello `settings/` del sito, attenzione a spazi e maiuscole |
| Risposta con `"error"` e il numero **403** o **404** | L'indirizzo nella seconda casella è sbagliato | Deve finire con `/test/api/offline-data.php` |
| Non succede niente quando premi il pulsante | L'operazione è già in corso | Aspetta: i pulsanti si riattivano da soli quando ha finito |
| Sul sito il menù vecchio resta ancora | Il browser mostra la copia salvata | Ricarica la pagina tenendo premuto `Ctrl` mentre premi `F5` |

Se l'errore non è in questa lista, **fai una foto della finestra** con il
testo dell'errore e passala a chi segue il sito: lì dentro c'è scritto tutto
quello che serve per capire il problema.

---

## Cose da sapere

- Puoi rilanciare l'aggiornamento **quante volte vuoi**: non si rompe niente,
  ogni volta riparte da capo dal gestionale.
- Il sito conserva **in automatico la copia precedente** del menù, quindi un
  errore è sempre recuperabile.
- La cartella del programma è **portatile**: puoi copiarla su una chiavetta e
  usarla da un altro PC della stessa rete.
