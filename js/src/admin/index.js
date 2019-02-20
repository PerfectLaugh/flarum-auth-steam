import app from 'flarum/app';
import SteamSettingsModal from './components/SteamSettingsModal';

app.initializers.add('PerfectLaugh-auth-steam', () => {
  app.extensionSettings['PerfectLaugh-auth-steam'] = () => app.modal.show(new SteamSettingsModal());
});
