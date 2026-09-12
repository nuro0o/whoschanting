import {createServer} from 'vite';
import vue from '@vitejs/plugin-vue';
const server=await createServer({configFile:false,plugins:[vue()],resolve:{alias:{'@':process.cwd()+'/resources/js'}},server:{host:'127.0.0.1',port:5175,strictPort:true}});await server.listen();server.printUrls();
