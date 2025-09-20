// Tutorial del cliente de Open Payments

// Objetivo: Realizar un pago entre pares entre dos direcciones de billetera (usando cuentas en la cuenta de prueba)

//https://ilp.interledger-test.dev/preba2 - Cliente
//https://ilp.interledger-test.dev/prueba21 - Remitente
//https://ilp.interledger-test.dev/goku - Receptor


// ConfiguraciÃ³n inicial
import { createAuthenticatedClient } from "@interledger/open-payments";
import fs from "fs";
import { isFinalizedGrant } from '@interledger/open-payments';
import  Readline  from "readline/promises";


// a. Importar dependencias y configurar el cliente
(async () => { 
    const privatekey = fs.readFileSync("private.key", "utf8");
    const client = await createAuthenticatedClient({
        walletAddressUrl: "https://ilp.interledger-test.dev/preba2",
        privateKey:'private.key',
        keyId: "5e98ed3f-aebb-44e6-aa32-4ff599f8a189",
    });
// b. Crear una instancia del cliente Open Payments
// c. Cargar la clave privada del archivo
// d. Configurar las direcciones de las billeteras del remitente y el receptor

// Flujo de pago entre pares
   
// 1. Obtener una concesiÃ³n para un pago entrante  
 const sendingWalletAdress = await client.walletAddress.get( {
        url: "https://ilp.interledger-test.dev/prueba21"
    });

    const receivingWalletAdress = await client.walletAddress.get( {
        url:"https://ilp.interledger-test.dev/goku"

    });

    console.log(sendingWalletAdress, receivingWalletAdress);
// 2. Obtener una concesiÃ³n para un pago entrante
const incomingPaymentGrant = await client.grant.request(
    {
        url: receivingWalletAdress.authServer,
    },
    {
        access_token:{
            access:[
                {
                    type: "incoming-payment",
                    actions: ["create", "read", "list"],
                }
            ]
        }
    }
);
if (!isFinalizedGrant(incomingPaymentGrant)) {
    throw new Error("EL pago entrante no se ha concedido correctamente");
}

console.log(incomingPaymentGrant);
// 3. Crear un pago entrante para el receptor
const incomingPaymet = await client.incomingPayment.create(
    {
        url: receivingWalletAdress.resourceServer,
        accessToken: incomingPaymentGrant.access_token.value,
    },
    {
        walletAddress: receivingWalletAdress.id,
        incomingAmount: {
            assetCode: receivingWalletAdress.assetCode,
            assetScale: receivingWalletAdress.assetScale,
            value: "1000",
        },
    }
);
console.log({incomingPaymet});    
// 4. Crear un concesiÃ³n para una cotizaciÃ³n
const quoteGrant = await client.grant.request(
    {
        url: sendingWalletAdress.authServer,
    },
    {
        access_token: {
            access: [
                {
                    type: "quote",
                    actions: ["create"],
                }
            ]
        }
    }
);

if (!isFinalizedGrant(quoteGrant)) {
    throw new Error("La cotizacion no se ha realizado correctamente");
}

console.log(quoteGrant);

// 5. Obtener una cotizaciÃ³n para el remitente
const quote = await client.quote.create(
    {
        url:receivingWalletAdress.resourceServer,
        accessToken: quoteGrant.access_token.value,
    },
    {
        walletAddress: sendingWalletAdress.id,
        receiver:  incomingPaymet.id,
        method: "ilp"
    }
);
console.log({quote});
// 6. Obtener una concesiÃ³n para un pago saliente
const outgoingPaymentGrant = await client.grant.request(
    {
        url: sendingWalletAdress.authServer,
    },
    {
        access_token: {
            access: [
                {
                    type: "outgoing-payment",
                    actions: ["create"],
                    limits: {
                        debitAmount: quote.debitAmount,
                    },
                    identifier: sendingWalletAdress.id,  
                }
            ]
        },
        interact: {
            start: [ "redirect" ], 
        },
    }
);
console.log({outgoingPaymentGrant});
// 7. Continuar con la concesiÃ³n del pago saliente
await Readline
    .createInterface({
        input: process.stdin,
        output: process.stdout,
    })
    .question("Presione enter para continuar");

// 8. Finalizar la concesiÃ³n del pago saliente
const finalizedOutgoingPaymentGrant = await client.grant.continue({
        url: outgoingPaymentGrant.continue.uri,
        accessToken: outgoingPaymentGrant.continue.access_token.value,
    });
    if(!isFinalizedGrant(finalizedOutgoingPaymentGrant)) {
        throw new Error("Se espera la Finalizacion");
    }

// 9. Continuar con la cotizaciÃ³n de pago saliente
const outgoingPayment = await client.outgoingPayment.create(
    {
        url: sendingWalletAdress.resourceServer,
        accessToken: finalizedOutgoingPaymentGrant.access_token.value,
    },
    {
        walletAddress: sendingWalletAdress.id,
        quoteId: quote.id,
    }
);
console.log({outgoingPayment})
   
})();  
