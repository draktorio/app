pipeline {
    agent any

    environment {
        SWARM_STACK_NAME = "app"
        DB_SERVICE = 'db'
        DB_USER = 'root'
        DB_PASSWORD = 'secret'
        DB_NAME = 'lena'
        FRONTEND_URL = 'http://192.168.0.1:8080'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }
        
        stage('Build Docker Images') {
            steps {
                script {
                    sh "docker build -f php.Dockerfile . -t draktorio/crudback ."
                    sh "docker build -f mysql.Dockerfile . -t draktorio/mysql ."
                }
            } 
        }
            
        stage('Deploy to Docekr Swarm') {
            steps {
                script {
                    sh '''
                        if ! docker info | grep -q "Swarm^ active"; then
                            docker swarm init || true
                        fi
                    '''
                    sh "docker stack deploy --with-registry-auth -c docker-compose.yaml ${SWARM_STACK_NAME}"
                }
            }
        } 
        
        stage('Run Tests') {
            steps {
                script {
                    echo "Ожидание запуска сервисов..."
                    sleep time: 30, until: 'SECONDS'
                    
                    echo 'Proverka dotupnosti front...'
                    sh """
                        if ! curl -fsS ${FRONTEND_URL}; then
                           echo 'Front nedoztupen'
                           exit 1
                        fi
                    """
                    
                    echo 'Polucheni ID kontejnera bd...'
                    def dbContainerId = sh(
                        script: "docker ps --filter name=${SWARM_STACK_NAME}_${DB_SERVICE} --format '{{.ID}}'",
                        returnStdout: true
                        ).trim()
                        
                        if (!dbContainerId) {
                            error("Kontainer db ne najden")
                        }
                        
                        echo 'Podklucheni k MySQL u proverka tablic...'
                        sh """
                            docekr exec ${dbContainerId} mysql -u${DB_USER} -p${DB_PASSWORD} -e 'USE ${DB_NAME}; SHOW TABLES;'
                        """
                }
            }
        }  
    }

    post {
        success {
            echo 'Deployment completed successfully!'
        }
        failure {
            echo 'Deployment failed!'
        }
        always {
            cleanWs()
        }
    }
}
