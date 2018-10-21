<?php

define('BR', PHP_EOL);
define('ACTIVAR_LOG', 0); #Mostrar mas mensajes
define('HV', 999999); #High Value
define('DURACION', 200000);
define('CANTIDAD_DE_COLAS', 10);
define('IA_MIN',0); #Mínimo intervalo entre arribos, fdp lineal
define('IA_MAX',100); #Máximo intervalo entre arribos, fdp lineal
define('TA_MIN', 200); #Mínimo tiempo de atención, fdp lineal
define('TA_MAX',350); #Máximo tiempo de atención, fdp lineal

$simulador=new simulador(CANTIDAD_DE_COLAS,DURACION);
$simulador->ejecutar();

class simulador {
    private $T=0; #Tiempo actual
    private $N=null; #Cantidad de puestos
    private $tiempo_final=null; #Duración de la simulación
    private $TPLL=0; #Tiempo de próxima llegada al sistema
    private $NT=0; #Cantidad de personas total en el sistema
    private $TPS=array(); #Tiempo de próxima salida por puesto
    private $NS=array(); #Cantidad de personas en puesto+cola
    private $STO=array(); #Sumatoria de tiempo ocioso por puesto
    private $ITO=array(); #Inicio de tiempo ocioso por puesto
    private $STA=array(); #Sumatoria de tiempo de atención por puesto
    private $SLL=0; #Sumatoria de llegadas del sistema
    private $SS=0; #Sumatoria de salidas del sistema
    
    /**
    * @param integer $this->N Cantidad de colas 
    * @param integer $this->tiempo_final Tiempo de ejecución
    * @return void
     */
    public function __construct($N,$tiempo_final){
        $this->N=$N;
        $this->tiempo_final=$tiempo_final;
        return $this;
    }

    public function ejecutar(){
        $this->inicio=microtime(true);
        $this->inicializar();

        do 
        {
            if ($this->TPLL <= $this->min($this->TPS) ) 
                $this->procesar_llegada($this->N);
            else $this->procesar_salida($this->N);
            $this->NT++;
        } while($this->T<=$this->tiempo_final);
        

        $this->vaciamiento();
        return $this->imprimir($this->N,$this->tiempo_final);
    }

    private function inicializar(){
        for ($i=0; $i < $this->N; $i++) {
            $this->TPS[$i]=HV; 
            $this->NS[$i]=0;
            $this->STO[$i]=0;
            $this->STA[$i]=0;
            $this->ITO[$i]=0;
        }
    }

    private function min($array){
        $min=HV;
        foreach ($array as $key => $value) {
            if($array[$key]<=$min)
                $min=$array[$key];
        }

        return $min;
    }

    private function procesar_llegada(){
        # Avanza el tiempo
        $this->T=$this->TPLL;
        if(ACTIVAR_LOG) echo 'Tiempo actual: '.$this->T.BR;
        if(ACTIVAR_LOG) echo 'Procesar llegada'.BR;
        $IA=$this->generar_intervalo_entre_arribos();
        if(ACTIVAR_LOG) echo 'Intervalo entre arribos:'.$IA.BR;
        # Evento futuro no condicional
        $this->TPLL=$this->T+$IA;
        $this->SLL=$this->SLL+$this->TPLL;
        $j=null;
        $k=HV;
        for ($i=0; $i < $this->N; $i++) {
            #Cola con menor cantidad de gente
            #Falta elección aleatoria entre misma cantidad
            if($this->NS[$i]<$k) 
            {
                #Misma cantidad de gente->selecciona la primera cola
                $k=$this->NS[$i];
                $j=$i;
            }
        }
        $this->NS[$j]++;
        if($this->NS[$j]==1){
            # Evento futuro condicional
            $this->STO[$j]=$this->STO[$j]+ $this->T - $this->ITO[$j];
            $TA=$this->generar_tiempo_de_atencion();
            if(ACTIVAR_LOG) echo 'Tiempo de atencion: '.$TA.BR;
            $this->STA[$j]=$this->STA[$j]+$TA;
            $this->TPS[$j]=$this->T+$TA;
        }
    }

    private function generar_intervalo_entre_arribos(){
        #fdp lineal
        return rand(IA_MIN,IA_MAX);
    }

    private function generar_tiempo_de_atencion(){
        #fdp lineal
        return rand(TA_MIN,TA_MAX);
    }

    private function procesar_salida(){
        $k=HV;
        for ($i=0; $i < $this->N; $i++) { 
            if($this->TPS[$i]<=$k){
                $k=$this->TPS[$i];
                $j=$i;
            }
        }
        # Avanza el tiempo
        $this->T=$this->TPS[$j];
        $this->SS=$this->SS+$this->TPS[$j];
        if(ACTIVAR_LOG) echo 'Tiempo actual: '.$this->T.BR;
        if(ACTIVAR_LOG) echo 'Procesar salida'.BR;
        $this->TPS[$j]=HV;
        $this->NS[$j]--;

        if($this->NS[$j]>=1){
            # Evento futuro condicional
            $TA=$this->generar_tiempo_de_atencion();
            $this->STA[$j]=$this->STA[$j]+$TA;
            $this->TPS[$j]=$this->T+$TA;
        }
        else{
            $this->ITO[$j]=$this->T;
        }
    }

    private function vaciamiento(){
        for ($i=0; $i < $this->N; $i++) { 
            $this->procesar_salida();
        }
    }

    private function imprimir(){
        echo 'Duracion de la simulación (CPU): '.(string)(microtime(true)-$this->inicio).BR;
        echo 'Variable Exógena de Control->Cantidad de colas: '.$this->N.BR;
        echo 'Variable Exógena de Control->Duración de la simulación: '.$this->tiempo_final.BR;
        $STA=0;
        echo 'Sumatoria de tiempos de llegada: '.$this->SLL.BR;
        echo 'Sumatoria de tiempos de salida: '.$this->SS.BR;
        for ($i=0; $i < $this->N; $i++) $STA=$STA+$this->STA[$i];
        echo 'Sumatoria de tiempos de atencion: '.$STA.BR;
        echo 'Personas atendidas: '.$this->NT.BR;
        $PPS= ($this->SS-$this->SLL)/$this->NT;
        echo 'Promedio de permanencia en el sistema: '.$PPS.BR;
        $PEC= ($this->SS-$this->SLL-$STA)/$this->NT;
        echo 'Promedio de espera en cola: '.$PEC.BR;
        for ($i=0; $i < $this->N; $i++) { 
            echo 'Puesto Nro: '.$i.BR;
            echo 'Variables endógenas de resultados'.BR;
            echo 'Sumatorias de tiempos de atencion del puesto: '.$this->STA[$i].BR;
            if($this->STO[$i]==0) $this->STO[$i]=$this->tiempo_final;
            echo 'Sumatoria de tiempo ocioso del puesto: '.$this->STO[$i].BR;
            echo 'Porcentaje de tiempo ocioso del puesto: '.($this->STO[$i]/$this->tiempo_final*100).'%'.BR;

        }
    }

}



