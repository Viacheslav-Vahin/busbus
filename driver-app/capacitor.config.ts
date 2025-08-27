import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.maxbus.driver',
    appName: 'MaxBus Driver',
    webDir: 'dist',
    server: { androidScheme: 'https' }, // обов'язково для fetch
};

export default config;
