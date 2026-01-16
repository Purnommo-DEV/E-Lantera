// resources/js/offline-db.js
import Dexie from 'dexie';

const db = new Dexie('ELanteraOfflineDB');

db.version(1).stores({
    pendingWarga: '++id, action, data, timestamp'  // action: 'store' atau 'update'
});

export default db;