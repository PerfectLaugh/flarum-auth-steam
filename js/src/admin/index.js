import app from 'flarum/app';

import SteamSettingsModal from './components/SteamSettingsModal';

app.initializers.add('flarum-auth-steam', () => {
  app.extensionSettings['flarum-auth-steam'] = () => app.modal.show(new SteamSettingsModal());
});
